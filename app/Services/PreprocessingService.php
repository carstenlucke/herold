<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;
use RuntimeException;

class PreprocessingService
{
    public function __construct(
        private readonly AIService $aiService,
    ) {}

    public function process(VoiceNote $note): void
    {
        $typeConfig = config("herold.types.{$note->type}");

        if (! $typeConfig) {
            throw new RuntimeException("Unknown voice note type: {$note->type}");
        }

        $systemPrompt = $typeConfig['preprocessing_prompt'];

        $userMessage = "Transcript:\n{$note->transcript}";

        if (! empty($note->metadata)) {
            $metadataLines = collect($note->metadata)
                ->map(fn ($value, $key) => "{$key}: {$value}")
                ->implode("\n");

            $userMessage .= "\n\nMetadata:\n{$metadataLines}";
        }

        $result = $this->aiService->chat($systemPrompt, $userMessage);

        $note->update([
            'processed_title' => $result['title'],
            'processed_body' => $result['body'],
            'status' => NoteStatus::PROCESSED,
        ]);
    }
}
