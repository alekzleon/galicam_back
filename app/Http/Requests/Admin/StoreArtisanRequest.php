<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreArtisanRequest extends FormRequest
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
            'history' => $this->filled('history') ? trim((string) $this->input('history')) : null,
            'contact' => $this->filled('contact') ? trim((string) $this->input('contact')) : null,
            'photo_alt' => $this->filled('photo_alt') ? trim((string) $this->input('photo_alt')) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
            'translations' => Localization::normalizeTranslations(
                $this->input('translations', []),
                ['name', 'history', 'contact', 'photo_alt']
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('artisans', 'slug')],
            'history' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'photo_alt' => ['nullable', 'string', 'max:255'],
            'translations' => ['nullable', 'array'],
            'translations.name' => ['nullable', 'array'],
            'translations.name.*' => ['nullable', 'string', 'max:255'],
            'translations.history' => ['nullable', 'array'],
            'translations.history.*' => ['nullable', 'string'],
            'translations.contact' => ['nullable', 'array'],
            'translations.contact.*' => ['nullable', 'string'],
            'translations.photo_alt' => ['nullable', 'array'],
            'translations.photo_alt.*' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
