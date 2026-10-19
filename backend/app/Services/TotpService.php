<?php

namespace App\Services;

class TotpService
{
    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $email): string
    {
        $issuer = config('app.name');
        $label = rawurlencode($issuer.':'.$email);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    public function matchingCounter(string $secret, string $code, ?int $lastUsedCounter = null): ?int
    {
        $currentCounter = intdiv(now()->timestamp, 30);

        foreach (range($currentCounter - 1, $currentCounter + 1) as $counter) {
            if ($counter < 0 || ($lastUsedCounter !== null && $counter <= $lastUsedCounter)) {
                continue;
            }

            if (hash_equals($this->codeForCounter($secret, $counter), $code)) {
                return $counter;
            }
        }

        return null;
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $message = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $bits = '';

        foreach (unpack('C*', $value) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $bits = '';

        foreach (str_split(rtrim(strtoupper($value), '=')) as $character) {
            $index = strpos(self::BASE32_ALPHABET, $character);
            if ($index === false) {
                throw new \InvalidArgumentException('The stored MFA secret is invalid.');
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
