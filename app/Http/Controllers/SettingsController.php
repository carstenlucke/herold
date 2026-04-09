<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SettingsController extends Controller
{
    public function index(): InertiaResponse
    {
        $user = Auth::user();

        return Inertia::render('Settings/Index', [
            'github' => [
                'owner' => config('herold.github.owner'),
                'repo' => config('herold.github.repo'),
            ],
            'totp' => [
                'confirmed' => $user->hasTotpEnabled(),
            ],
        ]);
    }
}
