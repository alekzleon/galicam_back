<?php

namespace App\Support;

use App\Models\EcommerceSetting;
use Illuminate\Http\Request;

class Currency
{
    public static function currentCurrency(?Request $request = null): string
    {
        $settings = EcommerceSetting::currencySettings();
        $availableCodes = collect($settings['available_currencies'])->pluck('code')->all();
        $requested = $request?->query('currency') ?: $request?->header('X-Currency');
        $currency = strtoupper(trim((string) $requested));

        if ($currency !== '' && in_array($currency, $availableCodes, true)) {
            return $currency;
        }

        return (string) data_get($settings, 'default_currency', 'MXN');
    }

    public static function money(float|int|null $amount, ?string $currency = null): ?array
    {
        if ($amount === null) {
            return null;
        }

        $settings = EcommerceSetting::currencySettings();
        $baseCurrency = (string) data_get($settings, 'base_currency', 'MXN');
        $currency = $currency ?: static::currentCurrency(request());
        $currency = strtoupper(trim($currency));
        $metadata = collect(data_get($settings, 'available_currencies', []))->firstWhere('code', $currency)
            ?: collect(data_get($settings, 'supported_currencies', []))->firstWhere('code', $currency);

        if (! $metadata) {
            $currency = (string) data_get($settings, 'default_currency', $baseCurrency);
            $metadata = collect(data_get($settings, 'available_currencies', []))->firstWhere('code', $currency);
        }

        $exchangeRate = (float) data_get($settings, "exchange_rates.{$currency}", 1);
        $precision = (int) data_get($settings, 'rounding.precision', data_get($metadata, 'decimals', 2));
        $mode = (string) data_get($settings, 'rounding.mode', 'round');
        $baseAmount = (float) $amount;
        $convertedAmount = static::round($baseAmount * $exchangeRate, $precision, $mode);

        return [
            'amount' => $convertedAmount,
            'currency' => $currency,
            'formatted' => static::format($convertedAmount, $currency, $metadata, $precision),
            'symbol' => (string) data_get($metadata, 'symbol', '$'),
            'decimals' => $precision,
            'base_amount' => round($baseAmount, 4),
            'base_currency' => $baseCurrency,
            'exchange_rate' => $exchangeRate,
        ];
    }

    public static function toBase(float|int|null $amount, ?string $currency = null): ?float
    {
        if ($amount === null) {
            return null;
        }

        $settings = EcommerceSetting::currencySettings();
        $currency = $currency ?: static::currentCurrency(request());
        $exchangeRate = (float) data_get($settings, "exchange_rates.{$currency}", 1);

        if ($exchangeRate <= 0) {
            return (float) $amount;
        }

        return round(((float) $amount) / $exchangeRate, 4);
    }

    protected static function round(float $amount, int $precision, string $mode): float
    {
        $factor = 10 ** $precision;

        return match ($mode) {
            'ceil' => ceil($amount * $factor) / $factor,
            'floor' => floor($amount * $factor) / $factor,
            default => round($amount, $precision),
        };
    }

    protected static function format(float $amount, string $currency, ?array $metadata, int $precision): string
    {
        $symbol = (string) data_get($metadata, 'symbol', '$');

        return $symbol . number_format($amount, $precision) . ' ' . $currency;
    }
}
