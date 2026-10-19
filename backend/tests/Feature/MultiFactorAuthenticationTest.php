<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_status_and_sensitive_routes_require_authentication(): void
    {
        $this->getJson('/api/mfa/status')->assertUnauthorized();
        $this->postJson('/api/mfa/setup', ['password' => 'SecurePass123!'])->assertUnauthorized();
        $this->postJson('/api/mfa/enable', ['password' => 'SecurePass123!', 'code' => '123456'])->assertUnauthorized();
        $this->postJson('/api/mfa/disable', ['password' => 'SecurePass123!', 'code' => '123456'])->assertUnauthorized();
        $this->getJson('/api/weather?lat=47.6&lon=-122.3')->assertUnauthorized();
        $this->postJson('/api/maps/search', ['query' => 'Seattle'])->assertUnauthorized();
    }

    public function test_enrollment_requires_password_and_verifies_a_code_before_enabling(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SecurePass123!')]);
        $this->actingAs($user);

        $this->postJson('/api/mfa/setup', ['password' => 'incorrect'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $setup = $this->postJson('/api/mfa/setup', ['password' => 'SecurePass123!'])
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_url']);
        $secret = $setup->json('secret');

        $this->postJson('/api/mfa/enable', [
            'password' => 'SecurePass123!',
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $response = $this->postJson('/api/mfa/enable', [
            'password' => 'SecurePass123!',
            'code' => $this->totpCode($secret),
        ])->assertOk()->assertJsonPath('enabled', true);

        $recoveryCodes = $response->json('recovery_codes');
        $this->assertCount(10, $recoveryCodes);
        $this->assertStringContainsString('-', $recoveryCodes[0]);

        $storedUser = $user->fresh();
        $this->assertNotNull($storedUser->mfa_enabled_at);
        $this->assertSame($secret, $storedUser->mfa_secret);
        $this->assertNotSame($secret, $storedUser->getRawOriginal('mfa_secret'));
        $this->assertArrayNotHasKey('mfa_secret', $storedUser->toArray());
        $this->assertCount(10, $storedUser->mfa_recovery_codes);
        $this->getJson('/api/mfa/status')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('recovery_codes_remaining', 10);
    }

    public function test_mfa_login_requires_a_valid_non_replayed_totp_code(): void
    {
        $user = $this->mfaUser();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
        ])->assertAccepted()->assertJsonPath('mfa_required', true);
        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();

        $this->postJson('/api/mfa/challenge', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $code = $this->totpCode($user->mfa_secret);
        $this->postJson('/api/mfa/challenge', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
        ])->assertAccepted();
        $this->postJson('/api/mfa/challenge', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_failed_mfa_challenges_are_rate_limited(): void
    {
        Cache::flush();
        $user = $this->mfaUser();
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
        ])->assertAccepted();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/mfa/challenge', ['code' => '000000'])->assertUnprocessable();
        }

        $this->postJson('/api/mfa/challenge', ['code' => '000000'])->assertTooManyRequests();
    }

    public function test_recovery_code_is_consumed_once_and_can_complete_mfa_login(): void
    {
        $recoveryCode = 'AABBCCDDEEFF';
        $user = $this->mfaUser([
            'mfa_recovery_codes' => [Hash::make($recoveryCode)],
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
        ])->assertAccepted();

        $this->postJson('/api/mfa/challenge', ['recovery_code' => 'AABBCC-DDEEFF'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
        $this->assertSame([], $user->fresh()->mfa_recovery_codes);

        $this->postJson('/api/logout');
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecurePass123!',
        ])->assertAccepted();
        $this->postJson('/api/mfa/challenge', ['recovery_code' => $recoveryCode])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_disabling_mfa_requires_password_and_a_valid_second_factor(): void
    {
        $user = $this->mfaUser();
        $this->actingAs($user);
        $code = $this->totpCode($user->mfa_secret);

        $this->postJson('/api/mfa/disable', [
            'password' => 'wrong-password',
            'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/mfa/disable', [
            'password' => 'SecurePass123!',
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->postJson('/api/mfa/disable', [
            'password' => 'SecurePass123!',
            'code' => $code,
        ])->assertOk()->assertJsonPath('enabled', false);

        $this->assertNull($user->fresh()->mfa_enabled_at);
        $this->assertNull($user->fresh()->mfa_secret);
        $this->getJson('/api/mfa/status')->assertOk()->assertJsonPath('enabled', false);
    }

    private function mfaUser(array $overrides = []): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('SecurePass123!'),
            'mfa_secret' => app(TotpService::class)->generateSecret(),
            'mfa_recovery_codes' => [],
            'mfa_enabled_at' => now(),
            ...$overrides,
        ]);

        return $user->fresh();
    }

    private function totpCode(string $secret): string
    {
        $counter = intdiv(now()->timestamp, 30);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }

        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        $message = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
