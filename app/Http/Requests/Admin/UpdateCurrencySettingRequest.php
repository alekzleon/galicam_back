<?php

namespace App\Http\Requests\Admin;

use App\Models\EcommerceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default') && ! $this->has('default_currency')) {
            $this->merge(['default_currency' => $this->input('default')]);
        }

        if ($this->has('base') && ! $this->has('base_currency')) {
            $this->merge(['base_currency' => $this->input('base')]);
        }

        if ($this->has('currencies') && ! $this->has('available_currencies')) {
            $this->merge(['available_currencies' => $this->input('currencies')]);
        }

        if (is_string($this->input('available_currencies'))) {
            $this->merge([
                'available_currencies' => collect(explode(',', (string) $this->input('available_currencies')))
                    ->map(fn (string $currency) => trim($currency))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        foreach (['base_currency', 'default_currency'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            }
        }

        if ($this->has('available_currencies') && is_array($this->input('available_currencies'))) {
            $this->merge([
                'available_currencies' => collect($this->input('available_currencies'))
                    ->map(fn ($currency) => is_array($currency) ? data_get($currency, 'code') : $currency)
                    ->map(fn ($currency) => strtoupper(trim((string) $currency)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }

        if ($this->has('exchange_rates') && is_array($this->input('exchange_rates'))) {
            $rates = [];

            foreach ($this->input('exchange_rates') as $code => $rate) {
                $rates[strtoupper(trim((string) $code))] = $rate;
            }

            $this->merge(['exchange_rates' => $rates]);
        }
    }

    public function rules(): array
    {
        $supportedCurrencies = collect(EcommerceSetting::supportedCurrencies())->pluck('code')->all();

        return [
            'base_currency' => ['required', 'string', Rule::in($supportedCurrencies)],
            'default_currency' => ['required', 'string', Rule::in($supportedCurrencies)],
            'available_currencies' => ['required', 'array', 'min:1'],
            'available_currencies.*' => ['required', 'string', Rule::in($supportedCurrencies)],
            'exchange_rates' => ['nullable', 'array'],
            'exchange_rates.*' => ['nullable', 'numeric', 'gt:0'],
            'rounding' => ['nullable', 'array'],
            'rounding.mode' => ['nullable', 'string', Rule::in(['round', 'ceil', 'floor'])],
            'rounding.precision' => ['nullable', 'integer', 'min:0', 'max:4'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $availableCurrencies = $this->input('available_currencies', []);

            if (! is_array($availableCurrencies)) {
                return;
            }

            if (! in_array($this->input('default_currency'), $availableCurrencies, true)) {
                $validator->errors()->add('default_currency', 'La moneda por defecto debe estar dentro de las monedas activas.');
            }

            foreach (array_keys($this->input('exchange_rates', [])) as $code) {
                if (! in_array($code, $availableCurrencies, true)) {
                    $validator->errors()->add('exchange_rates', "El tipo de cambio de {$code} solo puede configurarse si la moneda está activa.");
                }
            }
        });
    }
}
