<?php

namespace Tests\Unit;

use App\Services\TotpService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestableTotpService;

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

    private TestableTotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totp = new TestableTotpService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function verifyAt(string $secret, string $code, int $timestamp, ?int $lastCounter = null): ?int
    {
        Carbon::setTestNow(Carbon::createFromTimestamp($timestamp));

        return $this->totp->verify($secret, $code, $lastCounter);
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
        $this->assertNotNull($this->verifyAt(self::RFC_SECRET_BASE32, $expected, $timestamp));
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

            $this->assertNotNull($this->verifyAt($secret, $code, $now), "step {$step} should be accepted");
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
            $this->assertNull($this->verifyAt($secret, $code, $now), "step {$step} should be rejected");
        }
    }

    public function test_verify_rejects_malformed_codes(): void
    {
        $timestamp = 59;
        $secret = self::RFC_SECRET_BASE32;

        foreach (['', '28708', '2870820', ' 287082', '287082 ', '28708a', 'abcdef', '287 082'] as $code) {
            $this->assertNull($this->verifyAt($secret, $code, $timestamp), "'{$code}' should be rejected");
        }
    }

    public function test_verify_rejects_code_for_other_secret(): void
    {
        $code = $this->totp->codeAt(self::RFC_SECRET_BASE32, 59);

        $this->assertNull($this->verifyAt('JBSWY3DPEHPK3PXP', $code, 59));
    }

    public function test_verify_returns_matched_counter(): void
    {
        $now = 1111111111;
        $current = intdiv($now, TotpService::PERIOD);

        foreach ([-1, 0, 1] as $step) {
            $code = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now + $step * TotpService::PERIOD);

            $this->assertSame($current + $step, $this->verifyAt(self::RFC_SECRET_BASE32, $code, $now));
        }
    }

    public function test_verify_rejects_counter_at_or_below_last_used(): void
    {
        $now = 1111111111;
        $current = intdiv($now, TotpService::PERIOD);
        $code = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now);

        // RFC 6238 § 5.2: an OTP accepted once must not be accepted again.
        $this->assertNull($this->verifyAt(self::RFC_SECRET_BASE32, $code, $now, $current));
        $this->assertNull($this->verifyAt(self::RFC_SECRET_BASE32, $code, $now, $current + 1));
        $this->assertSame($current, $this->verifyAt(self::RFC_SECRET_BASE32, $code, $now, $current - 1));
    }

    public function test_verify_rejects_code_of_older_step_once_a_later_step_was_used(): void
    {
        $now = 1111111111;
        $current = intdiv($now, TotpService::PERIOD);
        $previous = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now - TotpService::PERIOD);

        $this->assertNull($this->verifyAt(self::RFC_SECRET_BASE32, $previous, $now, $current));
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
