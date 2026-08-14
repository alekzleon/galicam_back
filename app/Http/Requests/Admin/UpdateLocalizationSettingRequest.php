<?php

namespace App\Http\Requests\Admin;

use App\Models\EcommerceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocalizationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_language') && ! $this->has('default_locale')) {
            $this->merge(['default_locale' => $this->input('default_language')]);
        }

        if ($this->has('languages') && ! $this->has('available_locales')) {
            $this->merge(['available_locales' => $this->input('languages')]);
        }

        if (is_string($this->input('available_locales'))) {
            $this->merge([
                'available_locales' => collect(explode(',', (string) $this->input('available_locales')))
                    ->map(fn (string $locale) => trim($locale))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        if ($this->has('default_locale')) {
            $this->merge(['default_locale' => strtolower(trim((string) $this->input('default_locale')))]);
        }

        if ($this->has('available_locales') && is_array($this->input('available_locales'))) {
            $this->merge([
                'available_locales' => collect($this->input('available_locales'))
                    ->map(fn ($locale) => is_array($locale) ? data_get($locale, 'code') : $locale)
                    ->map(fn ($locale) => strtolower(trim((string) $locale)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    public function rules(): array
    {
        $supportedLocales = collect(EcommerceSetting::supportedLocales())->pluck('code')->all();

        return [
            'default_locale' => ['required', 'string', Rule::in($supportedLocales)],
            'available_locales' => ['required', 'array', 'min:1'],
            'available_locales.*' => ['required', 'string', Rule::in($supportedLocales)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $defaultLocale = $this->input('default_locale');
            $availableLocales = $this->input('available_locales', []);

            if (is_array($availableLocales) && ! in_array($defaultLocale, $availableLocales, true)) {
                $validator->errors()->add('default_locale', 'El idioma por defecto debe estar dentro de los idiomas activos.');
            }
        });
    }
}
