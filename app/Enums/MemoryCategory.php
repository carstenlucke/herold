<?php

namespace App\Enums;

enum MemoryCategory: string
{
    case DECISION = 'decision';
    case LEARNING = 'learning';
    case PREFERENCE = 'preference';
    case CONTEXT = 'context';
}
