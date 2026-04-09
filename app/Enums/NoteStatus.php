<?php

namespace App\Enums;

enum NoteStatus: string
{
    case RECORDED = 'recorded';
    case TRANSCRIBING = 'transcribing';
    case TRANSCRIBED = 'transcribed';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case SENDING = 'sending';
    case SENT = 'sent';
    case ERROR = 'error';

    public function uiGroup(): string
    {
        return match ($this) {
            self::RECORDED => 'Aufgenommen',
            self::TRANSCRIBING, self::TRANSCRIBED, self::PROCESSING, self::SENDING => 'Wird verarbeitet...',
            self::PROCESSED => 'Fertig',
            self::SENT => 'Gesendet',
            self::ERROR => 'Fehler',
        };
    }
}
