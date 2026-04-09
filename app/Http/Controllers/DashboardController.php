<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Models\VoiceNote;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'openNotes' => VoiceNote::query()->where('status', '!=', 'sent')->count(),
                'sentTickets' => VoiceNote::query()->where('status', 'sent')->count(),
                'memories' => Memory::query()->count(),
            ],
            'latestNotes' => VoiceNote::query()->latest()->limit(5)->get(),
        ]);
    }
}
