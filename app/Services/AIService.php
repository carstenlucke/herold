<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIService
{
    private string $apiKey;

    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('herold.openai.api_key')
            ?? throw new RuntimeException('OpenAI API key is not configured.');
    }

    public function transcribe(string $audioPath): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post("{$this->baseUrl}/audio/transcriptions", [
                'model' => 'gpt-4o-transcribe',
                'response_format' => 'text',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Whisper transcription failed: {$response->status()} — {$response->body()}"
            );
        }

        return trim($response->body());
    }

    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.3): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => 'gpt-5.4-mini',
                'temperature' => $temperature,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Chat completion failed: {$response->status()} — {$response->body()}"
            );
        }

        $content = $response->json('choices.0.message.content');
        $parsed = json_decode($content, true);

        if (! is_array($parsed) || ! isset($parsed['title'], $parsed['body'])) {
            throw new RuntimeException(
                'Chat response did not contain expected "title" and "body" keys.'
            );
        }

        return $parsed;
    }
}
