<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/webm,audio/ogg,audio/mp4'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
