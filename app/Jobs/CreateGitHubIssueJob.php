<?php

namespace App\Jobs;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;
use App\Services\GitHubService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateGitHubIssueJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $noteId)
    {
    }

    public function handle(GitHubService $service): void
    {
        $note = VoiceNote::query()->findOrFail($this->noteId);
        $issue = $service->createIssue($note->processed_title ?? 'Untitled', $note->processed_body ?? '', [
            config("herold.types.{$note->type}.github_label", 'type:general'),
        ]);

        $note->update([
            'github_issue_number' => $issue['number'],
            'github_issue_url' => $issue['url'],
            'status' => NoteStatus::SENT,
        ]);
    }
}
