<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stripe' => ['sometimes', 'array'],
            'stripe.enabled' => ['sometimes', 'boolean'],
            'paypal' => ['sometimes', 'array'],
            'paypal.enabled' => ['sometimes', 'boolean'],
        ];
    }
}
