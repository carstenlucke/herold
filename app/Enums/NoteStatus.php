<?php

namespace App\Enums;

enum NoteStatus: string
{
    case RECORDED = 'recorded';
    case PROCESSED = 'processed';
    case SENT = 'sent';
    case ERROR = 'error';
}
