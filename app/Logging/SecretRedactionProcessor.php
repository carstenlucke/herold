<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SecretRedactionProcessor implements ProcessorInterface
{
    private const REDACTED = '[REDACTED]';

    /**
     * Environment variable names whose values must be masked.
     */
    /**
     * Config keys whose values must be masked in logs.
     */
    private array $secretConfigKeys = [
        'app.key',
        'herold.api_key',
        'herold.github.token',
        'herold.openai.api_key',
    ];

    /**
     * Patterns that indicate sensitive data in log output.
     */
    private array $patterns;

    public function __construct()
    {
        $this->patterns = $this->buildPatterns();
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $this->redact($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra),
        );
    }

    private function buildPatterns(): array
    {
        $patterns = [];

        // Build patterns from actual config values
        foreach ($this->secretConfigKeys as $key) {
            $value = config($key);
            if ($value && strlen($value) >= 8) {
                $patterns[] = preg_quote($value, '/');
            }
        }

        // Authorization / Bearer header values
        $patterns[] = '(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*';

        // Generic Authorization header
        $patterns[] = '(Authorization:\s*)\S+';

        // Session tokens (common formats)
        $patterns[] = '(session[_\-]?(id|token)\s*[=:]\s*)\S+';

        return $patterns;
    }

    private function redact(string $value): string
    {
        foreach ($this->patterns as $pattern) {
            $value = preg_replace("/{$pattern}/i", self::REDACTED, $value) ?? $value;
        }

        return $value;
    }

    private const SENSITIVE_CONTEXT_KEYS = [
        'authorization', 'bearer', 'token', 'api_key', 'apikey',
        'secret', 'password', 'session_id', 'session_token', 'cookie',
    ];

    private function redactArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $result[$key] = self::REDACTED;
            } elseif (is_string($value)) {
                $result[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $result[$key] = $this->redactArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_CONTEXT_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
