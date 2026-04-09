<?php

namespace App\Models;

use App\Enums\NoteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoiceNote extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'type',
        'status',
        'audio_path',
        'transcript',
        'processed_title',
        'processed_body',
        'metadata',
        'github_issue_number',
        'github_issue_url',
        'error_message',
    ];

    protected $casts = [
        'metadata' => 'array',
        'status' => NoteStatus::class,
    ];
}
