<?php

namespace App\Services\Payments;

use App\Models\Region;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeConnectService
{
    public function ensureAccount(Region $region, ?string $email = null): Region
    {
        if (filled($region->stripe_account_id)) {
            return $region;
        }

        $payload = [
            'type' => config('services.stripe.connect_account_type', 'express'),
            'country' => config('services.stripe.connect_country', 'MX'),
            'email' => $email,
            'business_profile' => [
                'name' => $region->name,
                'product_description' => 'Venta de productos del centro regional ' . $region->name,
            ],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'region_id' => (string) $region->id,
                'region_slug' => $region->slug,
            ],
        ];

        $account = $this->post('https://api.stripe.com/v1/accounts', $payload);

        $region->forceFill([
            'stripe_account_id' => data_get($account, 'id'),
        ])->save();

        return $this->syncAccount($region);
    }

    public function createOnboardingLink(Region $region, ?string $email = null): array
    {
        $region = $this->ensureAccount($region, $email);

        $link = $this->post('https://api.stripe.com/v1/account_links', [
            'account' => $region->stripe_account_id,
            'refresh_url' => $this->connectUrl('refresh', $region),
            'return_url' => $this->connectUrl('return', $region),
            'type' => 'account_onboarding',
        ]);

        return [
            'region' => $this->statusPayload($region->fresh()),
            'account_link' => [
                'object' => data_get($link, 'object'),
                'created' => data_get($link, 'created'),
                'expires_at' => data_get($link, 'expires_at'),
                'url' => data_get($link, 'url'),
            ],
        ];
    }

    public function syncAccount(Region $region): Region
    {
        abort_if(blank($region->stripe_account_id), 422, 'El centro regional no tiene cuenta Stripe Connect.');

        $account = $this->get("https://api.stripe.com/v1/accounts/{$region->stripe_account_id}");
        $chargesEnabled = (bool) data_get($account, 'charges_enabled', false);
        $payoutsEnabled = (bool) data_get($account, 'payouts_enabled', false);
        $detailsSubmitted = (bool) data_get($account, 'details_submitted', false);

        $region->forceFill([
            'stripe_connect_status' => $this->statusFromAccount($detailsSubmitted, $chargesEnabled, $payoutsEnabled),
            'stripe_details_submitted' => $detailsSubmitted,
            'stripe_charges_enabled' => $chargesEnabled,
            'stripe_payouts_enabled' => $payoutsEnabled,
            'stripe_capabilities' => data_get($account, 'capabilities', []),
            'stripe_requirements' => data_get($account, 'requirements', []),
            'stripe_synced_at' => now(),
        ])->save();

        return $region;
    }

    public function statusPayload(Region $region): array
    {
        return [
            'region_id' => $region->id,
            'region_name' => $region->name,
            'region_slug' => $region->slug,
            'account_id' => $region->stripe_account_id,
            'status' => $region->stripe_connect_status,
            'details_submitted' => (bool) $region->stripe_details_submitted,
            'charges_enabled' => (bool) $region->stripe_charges_enabled,
            'payouts_enabled' => (bool) $region->stripe_payouts_enabled,
            'capabilities' => $region->stripe_capabilities ?? [],
            'requirements' => $region->stripe_requirements ?? [],
            'synced_at' => $region->stripe_synced_at?->toDateTimeString(),
            'is_ready_for_charges' => (bool) $region->stripe_charges_enabled,
            'is_ready_for_payouts' => (bool) $region->stripe_payouts_enabled,
        ];
    }

    protected function statusFromAccount(bool $detailsSubmitted, bool $chargesEnabled, bool $payoutsEnabled): string
    {
        if ($chargesEnabled && $payoutsEnabled) {
            return 'enabled';
        }

        if ($detailsSubmitted) {
            return 'submitted';
        }

        return 'pending_onboarding';
    }

    protected function connectUrl(string $type, Region $region): string
    {
        $baseUrl = match ($type) {
            'refresh' => config('services.stripe.connect_refresh_url'),
            default => config('services.stripe.connect_return_url'),
        };

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query([
            'region_id' => $region->id,
            'region_slug' => $region->slug,
        ]);
    }

    protected function get(string $url): array
    {
        try {
            return Http::withToken($this->secretKey())
                ->timeout(20)
                ->get($url)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = $this->stripeErrorMessage($exception, 'No fue posible consultar Stripe Connect.');
            throw new HttpException(422, $message, $exception);
        }
    }

    protected function post(string $url, array $payload): array
    {
        try {
            return Http::asForm()
                ->withToken($this->secretKey())
                ->timeout(20)
                ->post($url, $this->flatten($payload))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = $this->stripeErrorMessage($exception, 'No fue posible configurar Stripe Connect.');
            throw new HttpException(422, $message, $exception);
        }
    }

    protected function secretKey(): string
    {
        $secretKey = config('services.stripe.secret_key');

        abort_if(blank($secretKey), 500, 'Stripe no está configurado.');

        return $secretKey;
    }

    protected function stripeErrorMessage(RequestException $exception, string $fallback): string
    {
        $message = (string) data_get($exception->response?->json(), 'error.message', $fallback);

        if (str_contains($message, "signed up for Connect")) {
            return 'Stripe Connect no está habilitado en la cuenta de plataforma configurada. Entra a Stripe Dashboard, activa Connect y completa el perfil de plataforma antes de crear cuentas conectadas.';
        }

        return $message ?: $fallback;
    }

    protected function flatten(array $payload, ?string $prefix = null): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            $name = $prefix === null ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $result += $this->flatten($value, $name);
                continue;
            }

            $result[$name] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return $result;
    }
}
