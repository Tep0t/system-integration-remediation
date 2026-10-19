<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json([
            'enabled' => $user->mfa_enabled_at !== null,
            'recovery_codes_remaining' => count($user->mfa_recovery_codes ?? []),
        ]);
    }

    public function setup(Request $request, TotpService $totp): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if ($user->mfa_enabled_at !== null) {
            return response()->json(['message' => 'Multi-factor authentication is already enabled.'], 409);
        }

        $secret = $totp->generateSecret();
        $request->session()->put('mfa_setup_secret', Crypt::encryptString($secret));

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $totp->provisioningUri($secret, $user->email),
        ]);
    }

    public function enable(Request $request, TotpService $totp): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if ($user->mfa_enabled_at !== null) {
            return response()->json(['message' => 'Multi-factor authentication is already enabled.'], 409);
        }

        $encryptedSecret = $request->session()->get('mfa_setup_secret');
        if (! is_string($encryptedSecret)) {
            return response()->json(['message' => 'Start MFA setup again before enabling it.'], 422);
        }
        $secret = Crypt::decryptString($encryptedSecret);

        $counter = $totp->matchingCounter($secret, $validated['code']);
        if ($counter === null) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator code is invalid or expired.'],
            ]);
        }

        $recoveryCodes = collect(range(1, 10))
            ->map(fn (): string => strtoupper(bin2hex(random_bytes(6))))
            ->all();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), $recoveryCodes),
            'mfa_enabled_at' => now(),
            'mfa_last_used_step' => $counter,
        ])->save();

        $request->session()->forget('mfa_setup_secret');

        return response()->json([
            'enabled' => true,
            'recovery_codes' => array_map(
                fn (string $code): string => Str::substr($code, 0, 6).'-'.Str::substr($code, 6),
                $recoveryCodes,
            ),
        ]);
    }

    public function verifyLogin(Request $request, TotpService $totp): JsonResponse
    {
        $pendingUserId = $request->session()->get('mfa_pending_user_id');
        if (! is_numeric($pendingUserId)) {
            return response()->json(['message' => 'No multi-factor sign-in is pending.'], 401);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'digits:6'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        $this->assertSingleSecondFactor($validated);

        $user = User::find($pendingUserId);
        if (! $user || $user->mfa_enabled_at === null || ! is_string($user->mfa_secret)) {
            $request->session()->forget('mfa_pending_user_id');

            return response()->json(['message' => 'No multi-factor sign-in is pending.'], 401);
        }

        $verified = $this->consumeSecondFactor($user, $validated, $totp);
        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator or recovery code is invalid.'],
            ]);
        }

        Auth::login($user);
        $request->session()->forget('mfa_pending_user_id');
        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function disable(Request $request, TotpService $totp): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'required_without:recovery_code', 'digits:6'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        $this->assertSingleSecondFactor($validated);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        if ($user->mfa_enabled_at === null || ! is_string($user->mfa_secret)) {
            return response()->json(['message' => 'Multi-factor authentication is not enabled.'], 409);
        }

        if (! $this->consumeSecondFactor($user, $validated, $totp)) {
            throw ValidationException::withMessages([
                'code' => ['The authenticator or recovery code is invalid.'],
            ]);
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
            'mfa_last_used_step' => null,
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json(['enabled' => false]);
    }

    /**
     * @param  array{code?: string|null, recovery_code?: string|null}  $validated
     */
    private function consumeSecondFactor(User $user, array $validated, TotpService $totp): bool
    {
        return DB::transaction(function () use ($user, $validated, $totp): bool {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);
            if (! $lockedUser || $lockedUser->mfa_enabled_at === null || ! is_string($lockedUser->mfa_secret)) {
                return false;
            }

            if (isset($validated['code'])) {
                $counter = $totp->matchingCounter(
                    $lockedUser->mfa_secret,
                    $validated['code'],
                    $lockedUser->mfa_last_used_step,
                );

                if ($counter === null) {
                    return false;
                }

                $lockedUser->forceFill(['mfa_last_used_step' => $counter])->save();

                return true;
            }

            $providedCode = strtoupper(str_replace('-', '', $validated['recovery_code'] ?? ''));
            $recoveryCodes = $lockedUser->mfa_recovery_codes ?? [];
            foreach ($recoveryCodes as $index => $hashedCode) {
                if (Hash::check($providedCode, $hashedCode)) {
                    unset($recoveryCodes[$index]);
                    $lockedUser->forceFill([
                        'mfa_recovery_codes' => array_values($recoveryCodes),
                    ])->save();

                    return true;
                }
            }

            return false;
        });
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @param  array{code?: string|null, recovery_code?: string|null}  $validated
     */
    private function assertSingleSecondFactor(array $validated): void
    {
        if (isset($validated['code'], $validated['recovery_code'])) {
            throw ValidationException::withMessages([
                'code' => ['Provide either an authenticator code or a recovery code, not both.'],
            ]);
        }
    }
}
