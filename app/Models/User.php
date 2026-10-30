<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'api_key_hash', 'totp_secret', 'totp_confirmed_at'])]
#[Hidden(['password', 'remember_token', 'api_key_hash', 'totp_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'totp_last_counter' => 'integer',
        ];
    }

    public function hasTotpEnabled(): bool
    {
        return $this->totp_confirmed_at !== null;
    }

    /**
     * Atomically record $counter as the last accepted TOTP time step. Fails
     * when the same or a later step was already accepted (RFC 6238 § 5.2), so
     * one code can never complete two sign-ins, not even in parallel requests.
     */
    public function claimTotpCounter(int $counter): bool
    {
        $claimed = static::whereKey($this->getKey())
            ->where(fn ($query) => $query
                ->whereNull('totp_last_counter')
                ->orWhere('totp_last_counter', '<', $counter))
            ->update(['totp_last_counter' => $counter]) === 1;

        if ($claimed) {
            $this->setAttribute('totp_last_counter', $counter)->syncOriginalAttribute('totp_last_counter');
        }

        return $claimed;
    }
}
