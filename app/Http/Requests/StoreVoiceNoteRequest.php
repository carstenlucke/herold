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
        $rules = [
            'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4'],
            'type' => ['required', 'string', Rule::in(array_keys(config('herold.types', [])))],
            'metadata' => ['nullable', 'array'],
        ];

        $typeConfig = config("herold.types.{$this->input('type')}");

        foreach ($typeConfig['extra_fields'] ?? [] as $field) {
            $fieldRules = [];

            if ($field['required']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $fieldRules[] = match ($field['type']) {
                'url' => 'url',
                'date' => 'date_format:Y-m-d',
                default => 'string',
            };

            $rules["metadata.{$field['name']}"] = $fieldRules;
        }

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
            $typeConfig = config("herold.types.{$validated['type']}");
            $allowedKeys = collect($typeConfig['extra_fields'] ?? [])
                ->pluck('name')
                ->all();

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
}
