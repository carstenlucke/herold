<?php

namespace Tests\Support;

use App\Services\TotpService;

/**
 * Exposes TotpService internals to tests only, so the production auth
 * surface stays limited to generateSecret / provisioningUri / verify.
 */
class TestableTotpService extends TotpService
{
    /**
     * The TOTP code valid at the given Unix timestamp.
     */
    public function codeAt(string $secret, int $timestamp): string
    {
        return $this->codeForCounter($this->base32Decode($secret), intdiv($timestamp, self::PERIOD));
    }

    public function base32Encode(string $bytes): string
    {
        return parent::base32Encode($bytes);
    }

    public function base32Decode(string $input): string
    {
        return parent::base32Decode($input);
    }
}
