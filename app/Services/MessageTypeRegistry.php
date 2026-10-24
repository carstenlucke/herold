<?php

namespace App\Services;

class MessageTypeRegistry
{
    public const MAX_TEXT_SLOT_LENGTH = 255;

    public const MAX_URL_SLOT_LENGTH = 2048;

    public function all(): array
    {
        $types = config('herold.types', []);

        return array_map(function (array $type) {
            return collect($type)->except(['preprocessing_prompt', 'needs_current_date_context'])->all();
        }, $types);
    }

    public function get(string $type): ?array
    {
        return config("herold.types.{$type}");
    }

    public function keys(): array
    {
        return array_keys(config('herold.types', []));
    }

    /**
     * Validation rules for the type's metadata slots, keyed by "metadata.<name>".
     */
    public function metadataRules(string $type): array
    {
        $rules = [];

        foreach ($this->get($type)['extra_fields'] ?? [] as $field) {
            $rules["metadata.{$field['name']}"] = [
                $field['required'] ? 'required' : 'nullable',
                ...match ($field['type']) {
                    'url' => ['url', 'max:'.self::MAX_URL_SLOT_LENGTH],
                    'date' => ['date_format:Y-m-d'],
                    default => ['string', 'max:'.self::MAX_TEXT_SLOT_LENGTH],
                },
            ];
        }

        return $rules;
    }

    /**
     * Names of the metadata slots declared for the type.
     */
    public function slotNames(string $type): array
    {
        return array_column($this->get($type)['extra_fields'] ?? [], 'name');
    }
}
