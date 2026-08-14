<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGiftItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'code', 'description', 'unit_label'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        if ($this->has('is_active')) {
            $data['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('translations')) {
            $data['translations'] = Localization::normalizeTranslations(
                $this->input('translations', []),
                ['name', 'description', 'unit_label']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $giftItem = $this->route('giftItem');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                Rule::unique('gift_items', 'code')->ignore($giftItem?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.name' => ['nullable', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.description' => ['nullable', 'array'],
            'translations.description.*' => ['nullable', 'string'],
            'translations.unit_label' => ['nullable', 'array'],
            'translations.unit_label.*' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del artículo de regalo es obligatorio.',
            'code.unique' => 'El código del artículo de regalo ya existe.',
            'image.image' => 'El archivo debe ser una imagen.',
        ];
    }
}
