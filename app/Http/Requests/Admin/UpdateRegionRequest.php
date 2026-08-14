<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'description', 'banner_alt'] as $field) {
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

        if ($this->has('remove_banner')) {
            $data['remove_banner'] = filter_var($this->input('remove_banner'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('translations')) {
            $data['translations'] = Localization::normalizeTranslations(
                $this->input('translations', []),
                ['name', 'description', 'banner_alt']
            );
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $regionId = $this->route('region')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('regions', 'slug')->ignore($regionId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'banner' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'banner_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'remove_banner' => ['sometimes', 'nullable', 'boolean'],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.name' => ['nullable', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.description' => ['nullable', 'array'],
            'translations.description.*' => ['nullable', 'string'],
            'translations.banner_alt' => ['nullable', 'array'],
            'translations.banner_alt.*' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
