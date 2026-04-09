<?php

namespace App\Services;

use InvalidArgumentException;

class MessageTypeRegistry
{
    public function all(): array
    {
        return config('herold.types', []);
    }

    public function get(string $type): array
    {
        $config = $this->all();
        if (! isset($config[$type])) {
            throw new InvalidArgumentException("Unknown message type: {$type}");
        }

        return $config[$type];
    }
}
