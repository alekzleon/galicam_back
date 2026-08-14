<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRegionProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['description', 'banner_alt', 'request_notes'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        if ($this->has('remove_banner')) {
            $data['remove_banner'] = filter_var($this->input('remove_banner'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('translations')) {
            $data['translations'] = Localization::normalizeTranslations(
                $this->input('translations', []),
                ['description', 'banner_alt']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string'],
            'banner' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'banner_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remove_banner' => ['sometimes', 'nullable', 'boolean'],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.description' => ['nullable', 'array'],
            'translations.description.*' => ['nullable', 'string'],
            'translations.banner_alt' => ['nullable', 'array'],
            'translations.banner_alt.*' => ['nullable', 'string', 'max:255'],
            'request_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasChanges = $this->hasAny([
                'description',
                'banner',
                'banner_alt',
                'remove_banner',
                'translations',
            ]);

            if (! $hasChanges) {
                $validator->errors()->add('changes', 'Debes enviar al menos un cambio para revisión.');
            }
        });
    }
}
