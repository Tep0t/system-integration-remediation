<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_hashed_user_and_starts_a_session(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => '  Test.User@example.com ',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Test User')
            ->assertJsonPath('user.email', 'test.user@example.com')
            ->assertJsonMissingPath('user.password');

        $user = User::where('email', 'test.user@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_and_logout_use_the_session_guard(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'STUDENT@example.com',
            'password' => 'SecurePass123!',
        ])->assertOk()->assertJsonPath('user.email', 'student@example.com');

        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_failure_uses_a_generic_credentials_message(): void
    {
        $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'WrongPassword123!',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'unknown@example.com',
                'password' => 'WrongPassword123!',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'WrongPassword123!',
        ])->assertTooManyRequests();
    }

    public function test_password_recovery_response_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com']);

        $known->assertAccepted();
        $unknown->assertAccepted();
        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return parse_url($url, PHP_URL_HOST) === 'localhost'
                && parse_url($url, PHP_URL_PATH) === '/reset-password'
                && ($query['email'] ?? null) === $user->email
                && isset($query['token']);
        });
    }

    public function test_password_reset_token_is_hashed_single_use_and_changes_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $storedToken = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->value('token');

        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'UpdatedPass123!',
            'password_confirmation' => 'UpdatedPass123!',
        ];

        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('UpdatedPass123!', $user->fresh()->password));
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
    }

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'UpdatedPass123!',
            'password_confirmation' => 'UpdatedPass123!',
        ])->assertUnprocessable();

        $this->assertFalse(Hash::check('UpdatedPass123!', $user->fresh()->password));
    }

    public function test_cors_allows_the_configured_frontend_with_credentials(): void
    {
        $response = $this->get('/api/csrf-cookie', ['Origin' => 'http://localhost:3000']);

        $response
            ->assertOk()
            ->assertHeader('access-control-allow-origin', 'http://localhost:3000')
            ->assertHeader('access-control-allow-credentials', 'true')
            ->assertCookie('XSRF-TOKEN')
            ->assertJsonStructure(['csrf_token'])
            ->assertJsonMissingPath('csrf');

        $this->assertIsString($response->json('csrf_token'));
        $this->assertNotEmpty($response->json('csrf_token'));
    }
}
