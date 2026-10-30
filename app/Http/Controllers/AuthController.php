<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuthController extends Controller
{
    public function showLogin(Request $request, TotpService $totp): InertiaResponse
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
                    $provisioningUri = $totp->provisioningUri($user->totp_secret, $user->email);
                }
            }
        }

        return Inertia::render('Auth/Login', [
            'step' => $step,
            'needsSetup' => $needsSetup,
            'provisioningUri' => $provisioningUri,
        ]);
    }

    public function verifyKey(Request $request, TotpService $totp)
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
            $secret = $totp->generateSecret();
            $user->update(['totp_secret' => $secret]);
        }

        return redirect()->route('login');
    }

    public function confirmTotp(Request $request, TotpService $totp)
    {
        $request->validate([
            'totp_code' => 'required|digits:'.TotpService::DIGITS,
        ]);

        if (! $request->session()->get('auth.key_verified')) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($request->session()->get('auth.user_id'));

        // Never let factor 1 alone re-confirm a confirmed second factor.
        if ($user->hasTotpEnabled()) {
            return redirect()->route('login');
        }

        if (! $user->totp_secret) {
            return back()->withErrors(['totp_code' => 'TOTP not set up yet.']);
        }

        $counter = $totp->verify($user->totp_secret, $request->input('totp_code'), $user->totp_last_counter);

        if ($counter === null || ! $user->claimTotpCounter($counter)) {
            return back()->withErrors(['totp_code' => 'Invalid TOTP code.']);
        }

        $user->update(['totp_confirmed_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['auth.key_verified', 'auth.user_id']);

        return redirect()->intended(route('dashboard'));
    }

    public function verifyTotp(Request $request, TotpService $totp)
    {
        $request->validate([
            'totp_code' => 'required|digits:'.TotpService::DIGITS,
        ]);

        if (! $request->session()->get('auth.key_verified')) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($request->session()->get('auth.user_id'));

        if (! $user->hasTotpEnabled()) {
            return redirect()->route('login');
        }

        $counter = $totp->verify($user->totp_secret, $request->input('totp_code'), $user->totp_last_counter);

        // A replayed code (counter already claimed) fails exactly like a wrong one.
        if ($counter === null || ! $user->claimTotpCounter($counter)) {
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
        try {
            return User::sole();
        } catch (ModelNotFoundException|MultipleRecordsFoundException) {
            return null;
        }
    }
}
