<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuthController extends Controller
{
    public function showLogin(Request $request): InertiaResponse
    {
        $step = 'key';
        $needsSetup = false;
        $provisioningUri = null;

        if ($request->session()->get('auth.key_verified')) {
            $user = User::find($request->session()->get('auth.user_id'));
            if ($user) {
                $needsSetup = ! $user->hasTotpEnabled();
                $step = $needsSetup ? 'totp_setup' : 'totp';

                if ($needsSetup && $user->totp_secret) {
                    $provisioningUri = $this->buildProvisioningUri($user->totp_secret, $user->email);
                }
            }
        }

        return Inertia::render('Auth/Login', [
            'step' => $step,
            'needsSetup' => $needsSetup,
            'provisioningUri' => $provisioningUri,
        ]);
    }

    public function verifyKey(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
        ]);

        $user = $this->resolveAdminUser();

        if (! $user || ! $user->api_key_hash) {
            return back()->withErrors(['api_key' => 'Invalid API key.']);
        }

        $inputHash = hash('sha256', $request->input('api_key'));

        if (! hash_equals($user->api_key_hash, $inputHash)) {
            Log::warning('Failed API key verification attempt.', [
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['api_key' => 'Invalid API key.']);
        }

        $request->session()->put('auth.key_verified', true);
        $request->session()->put('auth.user_id', $user->id);

        $needsSetup = ! $user->hasTotpEnabled();

        if ($needsSetup) {
            $secret = $this->generateTotpSecret();
            $user->update(['totp_secret' => $secret]);
        }

        return redirect()->route('login');
    }

    public function setupTotp(Request $request)
    {
        if (! $request->session()->get('auth.key_verified')) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($request->session()->get('auth.user_id'));

        $secret = $this->generateTotpSecret();
        $user->update(['totp_secret' => $secret]);

        $provisioningUri = $this->buildProvisioningUri($secret, $user->email);

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $provisioningUri,
        ]);
    }

    public function confirmTotp(Request $request)
    {
        $request->validate([
            'totp_code' => 'required|string|size:6',
        ]);

        if (! $request->session()->get('auth.key_verified')) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($request->session()->get('auth.user_id'));

        if (! $user->totp_secret) {
            return back()->withErrors(['totp_code' => 'TOTP not set up yet.']);
        }

        if (! $this->verifyTotpCode($user->totp_secret, $request->input('totp_code'))) {
            return back()->withErrors(['totp_code' => 'Invalid TOTP code.']);
        }

        $user->update(['totp_confirmed_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['auth.key_verified', 'auth.user_id']);

        return redirect()->intended(route('dashboard'));
    }

    public function verifyTotp(Request $request)
    {
        $request->validate([
            'totp_code' => 'required|string|size:6',
        ]);

        if (! $request->session()->get('auth.key_verified')) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($request->session()->get('auth.user_id'));

        if (! $user->hasTotpEnabled()) {
            return redirect()->route('login');
        }

        if (! $this->verifyTotpCode($user->totp_secret, $request->input('totp_code'))) {
            Log::warning('Failed TOTP verification attempt.', [
                'ip' => $request->ip(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['totp_code' => 'Invalid TOTP code.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['auth.key_verified', 'auth.user_id']);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showRecovery(Request $request)
    {
        $recoveryPath = '.herold-recovery';

        if (! Storage::disk('local')->exists($recoveryPath)) {
            abort(404);
        }

        $lastModified = Storage::disk('local')->lastModified($recoveryPath);
        $ttlMinutes = 60;

        if (now()->timestamp - $lastModified > $ttlMinutes * 60) {
            Storage::disk('local')->delete($recoveryPath);
            abort(404);
        }

        return Inertia::render('Auth/Recovery');
    }

    public function processRecovery(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $recoveryPath = '.herold-recovery';

        if (! Storage::disk('local')->exists($recoveryPath)) {
            Log::warning('Recovery attempt with no recovery file.', [
                'ip' => $request->ip(),
            ]);
            abort(404);
        }

        $lastModified = Storage::disk('local')->lastModified($recoveryPath);
        $ttlMinutes = 60;

        if (now()->timestamp - $lastModified > $ttlMinutes * 60) {
            Storage::disk('local')->delete($recoveryPath);
            Log::warning('Recovery attempt with expired file.', [
                'ip' => $request->ip(),
            ]);
            abort(404);
        }

        $storedToken = trim(Storage::disk('local')->get($recoveryPath));
        $inputToken = trim($request->input('token'));

        if (! hash_equals($storedToken, $inputToken)) {
            Log::warning('Recovery attempt with invalid token.', [
                'ip' => $request->ip(),
            ]);
            abort(404);
        }

        Storage::disk('local')->delete($recoveryPath);

        $user = $this->resolveAdminUser();

        if (! $user) {
            abort(404);
        }

        $newApiKey = Str::random(64);

        $user->update([
            'api_key_hash' => hash('sha256', $newApiKey),
            'totp_secret' => null,
            'totp_confirmed_at' => null,
        ]);

        Log::info('Account recovered successfully.', [
            'ip' => $request->ip(),
            'user_id' => $user->id,
        ]);

        return Inertia::render('Auth/RecoverySuccess', [
            'apiKey' => $newApiKey,
        ]);
    }

    private function resolveAdminUser(): ?User
    {
        return User::where('email', config('herold.admin_email'))->first();
    }

    /**
     * Generate a random Base32-encoded TOTP secret (160 bits).
     */
    private function generateTotpSecret(): string
    {
        $bytes = random_bytes(20);
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $secret .= $base32Chars[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $secret .= $base32Chars[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $secret;
    }

    /**
     * Build a Google Authenticator compatible provisioning URI.
     */
    private function buildProvisioningUri(string $secret, string $accountName): string
    {
        $issuer = 'Herold';
        $label = rawurlencode("{$issuer}:{$accountName}");
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code using HMAC-SHA1 (RFC 6238).
     * Allows a +-1 time step window to account for clock drift.
     */
    private function verifyTotpCode(string $secret, string $code): bool
    {
        $timeStep = 30;
        $currentTimestamp = (int) floor(time() / $timeStep);

        // Check current and adjacent time steps (+-1 window)
        for ($offset = -1; $offset <= 1; $offset++) {
            $expectedCode = $this->generateTotpCode($secret, $currentTimestamp + $offset);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a 6-digit TOTP code for a given counter value.
     */
    private function generateTotpCode(string $base32Secret, int $counter): string
    {
        $secretBytes = $this->base32Decode($base32Secret);

        // Pack counter as 8-byte big-endian
        $counterBytes = pack('N*', 0, $counter);

        $hash = hash_hmac('sha1', $counterBytes, $secretBytes, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binary % 1_000_000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32-encoded string.
     */
    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        foreach (str_split($input) as $char) {
            $value = strpos($map, $char);
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
}
