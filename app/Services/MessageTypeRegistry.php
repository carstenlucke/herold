<?php

namespace App\Services;

class MessageTypeRegistry
{
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
}
