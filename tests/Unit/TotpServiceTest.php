<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Home-rolled TOTP (ADR-010) against RFC 6238 / RFC 4648.
 *
 * NFR-15a-01: Two-Factor Browser Authentication
 */
class TotpServiceTest extends TestCase
{
    /**
     * RFC 6238 Appendix B shared secret for SHA-1: ASCII "12345678901234567890".
     */
    private const RFC_SECRET_ASCII = '12345678901234567890';

    private const RFC_SECRET_BASE32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private TotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totp = new TotpService;
    }

    /**
     * RFC 6238 Appendix B, SHA-1 column, truncated to the 6 least significant digits.
     */
    public static function rfc6238Vectors(): array
    {
        return [
            'T=59' => [59, '287082'],
            'T=1111111109' => [1111111109, '081804'],
            'T=1111111111' => [1111111111, '050471'],
            'T=1234567890' => [1234567890, '005924'],
            'T=2000000000' => [2000000000, '279037'],
            'T=20000000000' => [20000000000, '353130'],
        ];
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_code_at_matches_rfc_6238_vector(int $timestamp, string $expected): void
    {
        $this->assertSame($expected, $this->totp->codeAt(self::RFC_SECRET_BASE32, $timestamp));
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_verify_accepts_rfc_6238_vector(int $timestamp, string $expected): void
    {
        $this->assertTrue($this->totp->verify(self::RFC_SECRET_BASE32, $expected, $timestamp));
    }

    public function test_base32_encodes_rfc_secret(): void
    {
        $this->assertSame(self::RFC_SECRET_BASE32, $this->totp->base32Encode(self::RFC_SECRET_ASCII));
    }

    public function test_base32_round_trip(): void
    {
        foreach (['', 'f', 'fo', 'foo', 'foob', 'fooba', 'foobar', random_bytes(20), random_bytes(33)] as $bytes) {
            $this->assertSame($bytes, $this->totp->base32Decode($this->totp->base32Encode($bytes)));
        }
    }

    public function test_base32_decode_accepts_lowercase_and_padding(): void
    {
        // RFC 4648 § 10 test vector
        $this->assertSame('foobar', $this->totp->base32Decode('mzxw6ytboi======'));
    }

    public function test_generated_secret_is_160_bit_base32(): void
    {
        $secret = $this->totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame(20, strlen($this->totp->base32Decode($secret)));
        $this->assertNotSame($secret, $this->totp->generateSecret());
    }

    public function test_verify_accepts_codes_within_one_step_of_drift(): void
    {
        $now = 1111111111;
        $secret = self::RFC_SECRET_BASE32;

        foreach ([-1, 0, 1] as $step) {
            $code = $this->totp->codeAt($secret, $now + $step * TotpService::PERIOD);

            $this->assertTrue($this->totp->verify($secret, $code, $now), "step {$step} should be accepted");
        }
    }

    public function test_verify_rejects_codes_two_steps_away(): void
    {
        $now = 1111111111;
        $secret = self::RFC_SECRET_BASE32;
        $window = [];

        foreach ([-1, 0, 1] as $step) {
            $window[] = $this->totp->codeAt($secret, $now + $step * TotpService::PERIOD);
        }

        foreach ([-2, 2] as $step) {
            $code = $this->totp->codeAt($secret, $now + $step * TotpService::PERIOD);

            // Guard against an (astronomically unlikely) collision with a code inside the window.
            $this->assertNotContains($code, $window);
            $this->assertFalse($this->totp->verify($secret, $code, $now), "step {$step} should be rejected");
        }
    }

    public function test_verify_rejects_malformed_codes(): void
    {
        $timestamp = 59;
        $secret = self::RFC_SECRET_BASE32;

        foreach (['', '28708', '2870820', ' 287082', '287082 ', '28708a', 'abcdef', '287 082'] as $code) {
            $this->assertFalse($this->totp->verify($secret, $code, $timestamp), "'{$code}' should be rejected");
        }
    }

    public function test_verify_rejects_code_for_other_secret(): void
    {
        $code = $this->totp->codeAt(self::RFC_SECRET_BASE32, 59);

        $this->assertFalse($this->totp->verify('JBSWY3DPEHPK3PXP', $code, 59));
    }

    public function test_provisioning_uri_carries_secret_and_parameters(): void
    {
        $uri = $this->totp->provisioningUri('JBSWY3DPEHPK3PXP', 'operator@example.com');

        $this->assertSame(
            'otpauth://totp/Herold%3Aoperator%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Herold&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }
}
