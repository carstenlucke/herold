<?php

namespace App\Http\Controllers;

use App\Enums\NoteStatus;
use App\Jobs\CreateGitHubIssueJob;
use App\Models\VoiceNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index()
    {
        return VoiceNote::query()->whereNotNull('github_issue_number')->latest()->get();
    }

    public function send(VoiceNote $note): RedirectResponse
    {
        $note->update(['status' => NoteStatus::SENDING]);
        CreateGitHubIssueJob::dispatch($note->id);

        return back();
    }

    public function updateStatus(Request $request, int $number): RedirectResponse
    {
        VoiceNote::query()->where('github_issue_number', $number)->update([
            'metadata' => array_merge($request->input('metadata', []), ['workflow_status' => $request->string('status')->toString()]),
        ]);

        return back();
    }
}
