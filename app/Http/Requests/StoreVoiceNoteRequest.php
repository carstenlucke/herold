<?php

namespace App\Http\Requests;

use App\Services\MessageTypeRegistry;
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
        $rules = [
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4'],
            'type' => ['required', 'string', Rule::in(array_keys(config('herold.types', [])))],
            'metadata' => ['nullable', 'array'],
        ];

        $rules += $this->typeRegistry()->metadataRules((string) $this->input('type'));

        return $rules;
    }

    /**
     * Filter validated metadata to only include keys defined in the type's extra_fields.
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        if (! empty($validated['metadata']) && isset($validated['type'])) {
            $allowedKeys = $this->typeRegistry()->slotNames($validated['type']);

            $validated['metadata'] = array_intersect_key(
                $validated['metadata'],
                array_flip($allowedKeys),
            );

            if (empty($validated['metadata'])) {
                $validated['metadata'] = null;
            }
        }

        return $validated;
    }

    private function typeRegistry(): MessageTypeRegistry
    {
        return app(MessageTypeRegistry::class);
    }
}
