<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRegionalCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region_id' => ['sometimes', 'required', 'integer', 'exists:regions,id'],
            'is_active' => ['sometimes', 'boolean'],
            'regional_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'regional_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'commission_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'region_id.required' => 'El centro regional es obligatorio.',
            'region_id.exists' => 'El centro regional seleccionado no existe.',
            'regional_price.numeric' => 'El precio regional debe ser numérico.',
            'regional_price.min' => 'El precio regional no puede ser negativo.',
            'regional_stock.numeric' => 'El stock regional debe ser numérico.',
            'regional_stock.min' => 'El stock regional no puede ser negativo.',
            'commission_rate.numeric' => 'La comisión debe ser numérica.',
            'commission_rate.min' => 'La comisión no puede ser negativa.',
            'commission_rate.max' => 'La comisión no puede ser mayor a 100%.',
        ];
    }
}
