<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncRegionProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required_without:products', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'products' => ['required_without:product_ids', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.is_active' => ['sometimes', 'boolean'],
            'products.*.regional_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products.*.regional_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'products.*.commission_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'products.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'products.*.metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
