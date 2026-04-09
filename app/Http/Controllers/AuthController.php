<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function verifyKey(Request $request): RedirectResponse
    {
        $request->session()->put('herold.api_key_verified', true);

        return back()->with('status', 'API key accepted. Enter your TOTP code.');
    }

    public function verifyTotp(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
