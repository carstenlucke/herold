<?php

namespace App\Services;

/**
 * Home-rolled TOTP (RFC 6238 on top of RFC 4226 HOTP): HMAC-SHA1,
 * 6 digits, 30-second steps, Base32-encoded shared secret.
 */
class TotpService
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    /**
     * Accepted clock drift in time steps on either side of the current step.
     */
    public const WINDOW = 1;

    private const ISSUER = 'Herold';

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32-encoded TOTP secret (160 bits).
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Build a Google Authenticator compatible provisioning URI.
     */
    public function provisioningUri(string $secret, string $accountName): string
    {
        $label = rawurlencode(self::ISSUER.":{$accountName}");
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => self::ISSUER,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code against the time step of $timestamp (default: now),
     * allowing a +-WINDOW step drift. Comparison is constant-time.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $key = $this->base32Decode($secret);
        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->codeForCounter($key, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The TOTP code valid at the given Unix timestamp.
     */
    public function codeAt(string $secret, int $timestamp): string
    {
        return $this->codeForCounter($this->base32Decode($secret), intdiv($timestamp, self::PERIOD));
    }

    /**
     * Encode raw bytes as unpadded RFC 4648 Base32.
     */
    public function base32Encode(string $bytes): string
    {
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::BASE32_ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $output .= self::BASE32_ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $output;
    }

    /**
     * Decode RFC 4648 Base32 (case-insensitive, padding optional;
     * characters outside the alphabet are skipped).
     */
    public function base32Decode(string $input): string
    {
        $input = strtoupper(rtrim($input, '='));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($input) as $char) {
            $value = strpos(self::BASE32_ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * HOTP value (RFC 4226) for a counter and raw (decoded) key,
     * zero-padded to DIGITS digits.
     */
    private function codeForCounter(string $key, int $counter): string
    {
        // 8-byte big-endian counter
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);

        // Dynamic truncation (RFC 4226 § 5.3)
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
