<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncArtisanProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
