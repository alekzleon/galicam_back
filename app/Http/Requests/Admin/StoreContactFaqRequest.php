<?php

namespace App\Http\Requests\Admin;

use App\Support\Localization;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'question' => $this->filled('question') ? trim((string) $this->input('question')) : null,
            'answer' => $this->filled('answer') ? trim((string) $this->input('answer')) : null,
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : true,
            'translations' => Localization::normalizeTranslations($this->input('translations', []), ['question', 'answer']),
        ]);
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'translations' => ['nullable', 'array'],
            'translations.question' => ['nullable', 'array'],
            'translations.question.*' => ['nullable', 'string', 'max:255'],
            'translations.answer' => ['nullable', 'array'],
            'translations.answer.*' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
