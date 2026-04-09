<?php

namespace App\Jobs;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;
use App\Services\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranscribeAudioJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $noteId)
    {
    }

    public function handle(AIService $aiService): void
    {
        $note = VoiceNote::query()->findOrFail($this->noteId);
        $transcript = $aiService->transcribe($note->audio_path);

        $note->update([
            'transcript' => $transcript,
            'status' => NoteStatus::TRANSCRIBED,
        ]);

        PreprocessTranscriptJob::dispatch($note->id);
    }
}
