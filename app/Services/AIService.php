<?php

namespace App\Services;

class AIService
{
    public function transcribe(string $audioPath): string
    {
        return 'Transcription placeholder for: '.$audioPath;
    }

    /** @return array{title:string,body:string} */
    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.3): array
    {
        return [
            'title' => 'Generated task from voice note',
            'body' => "## Prompt\n{$systemPrompt}\n\n## Transcript\n{$userMessage}",
        ];
    }
}
