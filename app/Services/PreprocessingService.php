<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Models\VoiceNote;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PreprocessingService
{
    public function __construct(
        private readonly AIService $aiService,
        private readonly MessageTypeRegistry $typeRegistry,
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
            // Generated content bypasses the edit boundary, so it is bounded here
            'processed_title' => mb_substr($result['title'], 0, GitHubService::MAX_TITLE_LENGTH),
            'processed_body' => $result['body'],
            'status' => NoteStatus::PROCESSED,
        ];

        // Merge type-specific extracted fields into metadata
        $extraFields = $typeConfig['extra_fields'] ?? [];
        $metadata = $note->metadata ?? [];
        $metadataChanged = false;

        foreach ($extraFields as $field) {
            $fieldName = $field['name'];
            if (! empty($result[$fieldName]) && $this->isValidSlotValue($note->type, $fieldName, $result[$fieldName])) {
                $metadata[$fieldName] = $result[$fieldName];
                $metadataChanged = true;
            }
        }

        if ($metadataChanged) {
            $updateData['metadata'] = $metadata;
        }

        $note->update($updateData);
    }

    /**
     * AI-extracted slot values must satisfy the same rules as operator input; invalid ones are dropped.
     */
    private function isValidSlotValue(string $type, string $fieldName, mixed $value): bool
    {
        $key = "metadata.{$fieldName}";

        return Validator::make(
            ['metadata' => [$fieldName => $value]],
            [$key => $this->typeRegistry->metadataRules($type)[$key]],
        )->passes();
    }
}
