<?php

namespace App\Support;

use App\Models\EcommerceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Localization
{
    public static function settings(): array
    {
        return EcommerceSetting::localizationSettings();
    }

    public static function currentLocale(?Request $request = null): string
    {
        $settings = static::settings();
        $availableLocales = collect(data_get($settings, 'available_locales', []))
            ->pluck('code')
            ->values()
            ->all();

        $requested = $request ? static::requestedLocale($request) : null;

        if ($requested && in_array($requested, $availableLocales, true)) {
            return $requested;
        }

        $userLocale = $request?->user()?->preferred_locale;

        if (is_string($userLocale) && in_array($userLocale, $availableLocales, true)) {
            return $userLocale;
        }

        return (string) data_get($settings, 'default_locale', 'en');
    }

    public static function translate(mixed $translations, string $field, mixed $fallback, ?string $locale = null): mixed
    {
        if (! is_array($translations)) {
            return $fallback;
        }

        $locale ??= (string) data_get(static::settings(), 'default_locale', 'en');
        $defaultLocale = (string) data_get(static::settings(), 'default_locale', 'en');

        foreach ([
            "{$field}.{$locale}",
            "{$field}.{$defaultLocale}",
            $field,
        ] as $path) {
            $value = Arr::get($translations, $path);

            if (filled($value)) {
                return $value;
            }
        }

        return $fallback;
    }

    public static function normalizeTranslations(mixed $translations, array $fields): array
    {
        if (is_string($translations)) {
            $decoded = json_decode($translations, true);

            $translations = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($translations)) {
            return [];
        }

        $locales = collect(static::settings()['supported_locales'])
            ->pluck('code')
            ->values()
            ->all();

        return collect($fields)
            ->mapWithKeys(function (string $field) use ($translations, $locales) {
                $values = collect($locales)
                    ->mapWithKeys(function (string $locale) use ($translations, $field) {
                        $value = data_get($translations, "{$field}.{$locale}");

                        return [$locale => filled($value) ? trim((string) $value) : null];
                    })
                    ->filter(fn ($value) => filled($value))
                    ->all();

                return [$field => $values];
            })
            ->filter(fn (array $values) => ! empty($values))
            ->all();
    }

    protected static function requestedLocale(Request $request): ?string
    {
        $locale = $request->query('locale')
            ?: $request->query('lang')
            ?: $request->header('X-Locale')
            ?: $request->header('Accept-Language');

        if (! is_string($locale) || blank($locale)) {
            return null;
        }

        $locale = Str::of($locale)
            ->lower()
            ->before(',')
            ->before(';')
            ->replace('_', '-')
            ->toString();

        return Str::before($locale, '-');
    }
}
