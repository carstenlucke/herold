<?php

namespace App\Models;

use App\Enums\NoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VoiceNote extends Model
{
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

    protected function casts(): array
    {
        return [
            'status' => NoteStatus::class,
            'metadata' => 'array',
        ];
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfStatus(Builder $query, NoteStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
