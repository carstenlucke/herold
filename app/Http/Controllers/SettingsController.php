<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Index', [
            'tokens' => $request->user()?->tokens()->get() ?? [],
        ]);
    }

    public function createToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['array'],
        ]);

        $token = $request->user()->createToken($data['name'], $data['abilities'] ?? [])->plainTextToken;

        return back()->with('newToken', $token);
    }

    public function revokeToken(Request $request, string $token): RedirectResponse
    {
        $request->user()->tokens()->where('id', $token)->delete();

        return back();
    }
}
