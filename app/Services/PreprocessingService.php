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

        if (! empty($typeConfig['needs_current_date_context'])) {
            $userMessage .= "\n\nCurrent date: ".now()->toDateString();
        }

        $result = $this->aiService->chat($systemPrompt, $userMessage);

        $updateData = [
            'processed_title' => $result['title'],
            'processed_body' => $result['body'],
            'status' => NoteStatus::PROCESSED,
        ];

        // Merge type-specific extracted fields into metadata
        $extraFields = $typeConfig['extra_fields'] ?? [];
        $metadata = $note->metadata ?? [];
        $metadataChanged = false;

        foreach ($extraFields as $field) {
            $fieldName = $field['name'];
            if (! empty($result[$fieldName])) {
                $metadata[$fieldName] = $result[$fieldName];
                $metadataChanged = true;
            }
        }

        if ($metadataChanged) {
            $updateData['metadata'] = $metadata;
        }

        $note->update($updateData);
    }
}
