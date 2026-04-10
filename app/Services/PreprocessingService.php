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

        if ($note->type === 'diary') {
            $userMessage .= "\n\nCurrent date: " . now()->toDateString();
        }

        $result = $this->aiService->chat($systemPrompt, $userMessage);

        $updateData = [
            'processed_title' => $result['title'],
            'processed_body' => $result['body'],
            'status' => NoteStatus::PROCESSED,
        ];

        // Merge type-specific extracted fields into metadata
        if ($note->type === 'diary' && ! empty($result['entry_date'])) {
            $metadata = $note->metadata ?? [];
            $metadata['entry_date'] = $result['entry_date'];
            $updateData['metadata'] = $metadata;
        }

        $note->update($updateData);
    }
}
