<?php

namespace App\Jobs;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;
use App\Services\PreprocessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PreprocessTranscriptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $noteId)
    {
    }

    public function handle(PreprocessingService $service): void
    {
        $note = VoiceNote::query()->findOrFail($this->noteId);
        $note->update(['status' => NoteStatus::PROCESSING]);
        $service->process($note);
    }
}
