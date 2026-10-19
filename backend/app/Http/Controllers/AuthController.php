<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrfCookie(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()]);
    }

    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->publicUser($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->normalizeEmail($request);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        if ($user->mfa_enabled_at !== null) {
            $request->session()->regenerate();
            $request->session()->put('mfa_pending_user_id', $user->id);

            return response()->json(['mfa_required' => true], 202);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->publicUser($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $validated['email']]);
        $nonActionableStatuses = [
            Password::RESET_LINK_SENT,
            Password::INVALID_USER,
            Password::RESET_THROTTLED,
        ];

        if (! in_array($status, $nonActionableStatuses, true)) {
            throw new \RuntimeException('Password reset notification could not be sent.');
        }

        return response()->json([
            'message' => 'If an account exists for that email, password reset instructions will be sent.',
        ], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This password reset link is invalid or has expired.',
            ], 422);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    private function passwordRule(): PasswordRule
    {
        return PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols();
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    private function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
