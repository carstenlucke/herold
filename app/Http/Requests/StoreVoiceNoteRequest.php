<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoiceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/webm,audio/ogg,audio/mp4'],
            'type' => ['required', 'string', Rule::in(array_keys(config('herold.types', [])))],
            'metadata' => ['nullable', 'array'],
            'metadata.youtube_url' => ['required_if:type,youtube', 'nullable', 'url'],
        ];
    }
}
