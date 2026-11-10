<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRegionProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('review_notes')) {
            $this->merge([
                'review_notes' => $this->filled('review_notes') ? trim((string) $this->input('review_notes')) : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
