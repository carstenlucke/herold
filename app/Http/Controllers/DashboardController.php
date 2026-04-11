<?php

namespace App\Http\Controllers;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => VoiceNote::count(),
                'recorded' => VoiceNote::ofStatus(NoteStatus::RECORDED)->count(),
                'processed' => VoiceNote::ofStatus(NoteStatus::PROCESSED)->count(),
                'sent' => VoiceNote::ofStatus(NoteStatus::SENT)->count(),
                'error' => VoiceNote::ofStatus(NoteStatus::ERROR)->count(),
            ],
            'recentNotes' => VoiceNote::latest()->limit(5)->get(),
        ]);
    }
}
