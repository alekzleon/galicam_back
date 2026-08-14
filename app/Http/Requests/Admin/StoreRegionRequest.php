<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'banner_alt' => $this->filled('banner_alt') ? trim((string) $this->input('banner_alt')) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
            'translations' => Localization::normalizeTranslations(
                $this->input('translations', []),
                ['name', 'description', 'banner_alt']
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('regions', 'slug')],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'banner_alt' => ['nullable', 'string', 'max:255'],
            'translations' => ['nullable', 'array'],
            'translations.name' => ['nullable', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.description' => ['nullable', 'array'],
            'translations.description.*' => ['nullable', 'string'],
            'translations.banner_alt' => ['nullable', 'array'],
            'translations.banner_alt.*' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
