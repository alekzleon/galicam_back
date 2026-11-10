<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateArtisanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'history', 'contact', 'photo_alt'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
            }
        }

        if ($this->has('slug')) {
            $data['slug'] = $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null;
        }

        if ($this->has('is_active')) {
            $data['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('remove_photo')) {
            $data['remove_photo'] = filter_var($this->input('remove_photo'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('translations')) {
            $data['translations'] = Localization::normalizeTranslations(
                $this->input('translations', []),
                ['name', 'history', 'contact', 'photo_alt']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $artisanId = $this->route('artisan')?->id;

        return [
            'region_id' => ['sometimes', 'required', 'integer', 'exists:regions,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('artisans', 'slug')->ignore($artisanId)],
            'history' => ['sometimes', 'nullable', 'string'],
            'contact' => ['sometimes', 'nullable', 'string'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'photo_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remove_photo' => ['sometimes', 'nullable', 'boolean'],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.name' => ['nullable', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.history' => ['nullable', 'array'],
            'translations.history.*' => ['nullable', 'string'],
            'translations.contact' => ['nullable', 'array'],
            'translations.contact.*' => ['nullable', 'string'],
            'translations.photo_alt' => ['nullable', 'array'],
            'translations.photo_alt.*' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'products' => ['sometimes', 'nullable', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
