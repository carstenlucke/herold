<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;

class PreprocessingService
{
    public function __construct(
        private readonly AIService $aiService,
        private readonly MessageTypeRegistry $registry,
    ) {
    }

    public function process(VoiceNote $note): void
    {
        $type = $this->registry->get($note->type);
        $prompt = $type['preprocessing_prompt'] ?? 'Create a concise issue.';
        $reply = $this->aiService->chat($prompt, $note->transcript ?? '');

        $note->update([
            'processed_title' => $reply['title'],
            'processed_body' => $reply['body'],
            'status' => NoteStatus::PROCESSED,
        ]);
    }
}
