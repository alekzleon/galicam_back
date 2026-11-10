<?php

namespace App\Services\Payments;

use App\Models\EcommerceSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaypalWebhookEvent;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaypalPaymentService
{
    public function __construct(
        protected OrderPaymentFinalizer $paymentFinalizer
    ) {
    }

    public function createOrder(Order $order): array
    {
        abort_unless((bool) data_get(EcommerceSetting::paymentMethodSettings(), 'paypal.enabled'), 422, 'PayPal no está habilitado.');
        abort_unless($order->isPendingPayment(), 422, 'El pedido no está pendiente de pago.');
        abort_unless((float) $order->total > 0, 422, 'El total del pedido debe ser mayor a cero.');

        $order->loadMissing(['items', 'user']);
        $this->paymentFinalizer->validateOrderStock($order);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order->id,
                'custom_id' => (string) $order->id,
                'invoice_id' => $order->number,
                'description' => 'Pedido ' . $order->number,
                'amount' => [
                    'currency_code' => strtoupper($order->currency),
                    'value' => $this->money((float) $order->total),
                ],
            ]],
            'application_context' => [
                'brand_name' => config('app.name'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $this->returnUrl($order),
                'cancel_url' => $this->cancelUrl($order),
            ],
        ];

        try {
            $paypalOrder = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->withHeaders(['PayPal-Request-Id' => "order-{$order->id}-paypal-create"])
                ->post($this->baseUrl() . '/v2/checkout/orders', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'message', 'No fue posible iniciar el pago con PayPal.');
            throw new HttpException(422, $message, $exception);
        }

        $paypalOrderId = data_get($paypalOrder, 'id');

        $order->forceFill([
            'paypal_order_id' => $paypalOrderId,
            'payment_method' => 'paypal',
        ])->save();

        Payment::updateOrCreate(
            [
                'provider' => 'paypal',
                'paypal_order_id' => $paypalOrderId,
            ],
            [
                'order_id' => $order->id,
                'status' => Order::PAYMENT_PENDING,
                'payment_method' => 'paypal',
                'amount' => (float) $order->total,
                'currency' => strtoupper($order->currency),
                'provider_payload' => $paypalOrder,
            ]
        );

        return $this->payload($order->fresh(), $paypalOrder);
    }

    public function captureOrder(string $paypalOrderId): ?Order
    {
        $order = Order::query()
            ->with(['items', 'payments'])
            ->where('paypal_order_id', $paypalOrderId)
            ->first();

        if (! $order) {
            return null;
        }

        if ($order->payment_status === Order::PAYMENT_PAID) {
            return $order->fresh(['items', 'payments']);
        }

        try {
            $capture = Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Id' => "order-{$order->id}-paypal-capture",
                ])
                ->withBody('{}', 'application/json')
                ->post($this->baseUrl() . "/v2/checkout/orders/{$paypalOrderId}/capture")
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'message', 'No fue posible capturar el pago con PayPal.');
            throw new HttpException(422, $message, $exception);
        }

        $this->syncCapturedOrder($order, $capture);

        return $order->fresh(['items', 'payments']);
    }

    public function handleWebhook(string $payload, array $headers): array
    {
        $event = json_decode($payload, true);

        abort_unless(is_array($event), 400, 'Payload inválido.');
        $this->validateWebhook($event, $headers);

        return DB::transaction(function () use ($event) {
            $eventId = (string) data_get($event, 'id');
            $type = (string) data_get($event, 'event_type');

            abort_if(blank($eventId) || blank($type), 400, 'Evento inválido.');

            $webhookEvent = PaypalWebhookEvent::query()
                ->where('paypal_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($webhookEvent && $webhookEvent->status === 'processed') {
                return [
                    'ok' => true,
                    'duplicate' => true,
                    'event_id' => $eventId,
                    'type' => $type,
                ];
            }

            $webhookEvent ??= PaypalWebhookEvent::create([
                'paypal_event_id' => $eventId,
                'type' => $type,
                'status' => 'processing',
                'payload' => $event,
            ]);

            match ($type) {
                'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted(data_get($event, 'resource', [])),
                'PAYMENT.CAPTURE.DENIED',
                'PAYMENT.CAPTURE.DECLINED',
                'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => $this->handlePaymentFailed(data_get($event, 'resource', [])),
                default => null,
            };

            $webhookEvent->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'payload' => $event,
            ])->save();

            return [
                'ok' => true,
                'duplicate' => false,
                'event_id' => $eventId,
                'type' => $type,
            ];
        });
    }

    protected function syncCapturedOrder(Order $order, array $capture): void
    {
        $captureId = $this->captureId($capture);
        $status = strtoupper((string) data_get($capture, 'status'));

        $this->syncPayment($order, [
            'status' => $status === 'COMPLETED' ? Order::PAYMENT_PAID : Order::PAYMENT_PENDING,
            'paypal_order_id' => data_get($capture, 'id', $order->paypal_order_id),
            'paypal_capture_id' => $captureId,
            'amount' => $this->amountFromPaypal($capture, $order),
            'currency' => $this->currencyFromPaypal($capture, $order),
            'payload' => $capture,
        ]);

        if ($status === 'COMPLETED') {
            $this->paymentFinalizer->markPaid($order, 'paypal', [
                'paypal_order_id' => data_get($capture, 'id', $order->paypal_order_id),
                'paypal_capture_id' => $captureId ?: $order->paypal_capture_id,
            ]);
        }
    }

    protected function handleCaptureCompleted(array $resource): void
    {
        $order = $this->findOrderFromPaypalResource($resource);

        if (! $order) {
            return;
        }

        $captureId = (string) data_get($resource, 'id');

        $this->syncPayment($order, [
            'status' => Order::PAYMENT_PAID,
            'paypal_order_id' => data_get($resource, 'supplementary_data.related_ids.order_id', $order->paypal_order_id),
            'paypal_capture_id' => $captureId,
            'amount' => (float) data_get($resource, 'amount.value', $order->total),
            'currency' => strtoupper((string) data_get($resource, 'amount.currency_code', $order->currency)),
            'payload' => $resource,
        ]);

        $this->paymentFinalizer->markPaid($order, 'paypal', [
            'paypal_order_id' => data_get($resource, 'supplementary_data.related_ids.order_id', $order->paypal_order_id),
            'paypal_capture_id' => $captureId ?: $order->paypal_capture_id,
        ]);
    }

    protected function handlePaymentFailed(array $resource): void
    {
        $order = $this->findOrderFromPaypalResource($resource);

        if (! $order) {
            return;
        }

        $captureId = (string) data_get($resource, 'id');

        $this->syncPayment($order, [
            'status' => Order::PAYMENT_FAILED,
            'paypal_order_id' => data_get($resource, 'supplementary_data.related_ids.order_id', $order->paypal_order_id),
            'paypal_capture_id' => $captureId ?: null,
            'amount' => (float) data_get($resource, 'amount.value', $order->total),
            'currency' => strtoupper((string) data_get($resource, 'amount.currency_code', $order->currency)),
            'payload' => $resource,
        ]);

        $this->paymentFinalizer->markFailed($order, 'paypal', [
            'paypal_capture_id' => $captureId ?: $order->paypal_capture_id,
        ]);
    }

    protected function syncPayment(Order $order, array $data): Payment
    {
        $paypalOrderId = $data['paypal_order_id'] ?? $order->paypal_order_id;
        $paypalCaptureId = $data['paypal_capture_id'] ?? $order->paypal_capture_id;

        $payment = Payment::query()
            ->where('provider', 'paypal')
            ->where(function ($query) use ($paypalOrderId, $paypalCaptureId) {
                $query
                    ->when(filled($paypalOrderId), fn ($subQuery) => $subQuery->orWhere('paypal_order_id', $paypalOrderId))
                    ->when(filled($paypalCaptureId), fn ($subQuery) => $subQuery->orWhere('paypal_capture_id', $paypalCaptureId));
            })
            ->first();

        $payment ??= new Payment(['provider' => 'paypal']);

        $payment->fill([
            'order_id' => $order->id,
            'status' => $data['status'],
            'payment_method' => 'paypal',
            'paypal_order_id' => $paypalOrderId,
            'paypal_capture_id' => $paypalCaptureId,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'paid_at' => $data['status'] === Order::PAYMENT_PAID ? now() : null,
            'provider_payload' => $data['payload'],
        ])->save();

        return $payment;
    }

    protected function validateWebhook(array $event, array $headers): void
    {
        abort_if(blank(config('services.paypal.webhook_id')), 500, 'PayPal webhook no está configurado.');

        $payload = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? null,
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? null,
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? null,
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? null,
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? null,
            'webhook_id' => config('services.paypal.webhook_id'),
            'webhook_event' => $event,
        ];

        abort_if(collect($payload)->except('webhook_event')->contains(fn ($value) => blank($value)), 400, 'Faltan headers de PayPal.');

        try {
            $verification = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($this->baseUrl() . '/v1/notifications/verify-webhook-signature', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'message', 'No fue posible validar el webhook de PayPal.');
            throw new HttpException(400, $message, $exception);
        }

        abort_unless(data_get($verification, 'verification_status') === 'SUCCESS', 400, 'Firma de PayPal inválida.');
    }

    protected function accessToken(): string
    {
        $clientId = trim((string) config('services.paypal.client_id'));
        $clientSecret = trim((string) config('services.paypal.client_secret'));

        abort_if(blank($clientId) || blank($clientSecret), 500, 'PayPal no está configurado.');

        try {
            return (string) Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout(20)
                ->post($this->baseUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json('access_token');
        } catch (RequestException $exception) {
            $message = data_get($exception->response?->json(), 'error_description', 'No fue posible autenticar con PayPal.');
            $message = $message === 'Client Authentication failed'
                ? 'PayPal rechazó las credenciales. Verifica que PAYPAL_CLIENT_ID y PAYPAL_CLIENT_SECRET sean de la app Sandbox correcta y que PAYPAL_MODE=sandbox.'
                : $message;

            throw new HttpException(422, $message, $exception);
        }
    }

    protected function findOrderFromPaypalResource(array $resource): ?Order
    {
        $paypalOrderId = data_get($resource, 'supplementary_data.related_ids.order_id')
            ?: data_get($resource, 'id');
        $captureId = data_get($resource, 'id');
        $invoiceId = data_get($resource, 'invoice_id');
        $customId = data_get($resource, 'custom_id');

        return Order::query()
            ->when($customId, fn ($query) => $query->orWhereKey($customId))
            ->when($invoiceId, fn ($query) => $query->orWhere('number', $invoiceId))
            ->when($paypalOrderId, fn ($query) => $query->orWhere('paypal_order_id', $paypalOrderId))
            ->when($captureId, fn ($query) => $query->orWhere('paypal_capture_id', $captureId))
            ->first();
    }

    protected function payload(Order $order, array $paypalOrder): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'paypal_order_id' => data_get($paypalOrder, 'id', $order->paypal_order_id),
            'approve_url' => collect(data_get($paypalOrder, 'links', []))->firstWhere('rel', 'approve')['href'] ?? null,
            'payment_status' => $order->payment_status,
            'amount' => (float) $order->total,
            'currency' => strtoupper($order->currency),
        ];
    }

    protected function captureId(array $capture): ?string
    {
        return data_get($capture, 'purchase_units.0.payments.captures.0.id')
            ?: data_get($capture, 'id');
    }

    protected function amountFromPaypal(array $capture, Order $order): float
    {
        return (float) (data_get($capture, 'purchase_units.0.payments.captures.0.amount.value')
            ?: data_get($capture, 'amount.value')
            ?: $order->total);
    }

    protected function currencyFromPaypal(array $capture, Order $order): string
    {
        return strtoupper((string) (data_get($capture, 'purchase_units.0.payments.captures.0.amount.currency_code')
            ?: data_get($capture, 'amount.currency_code')
            ?: $order->currency));
    }

    protected function returnUrl(Order $order): string
    {
        return $this->urlWithOrder(config('services.paypal.success_url'), $order);
    }

    protected function cancelUrl(Order $order): string
    {
        return $this->urlWithOrder(config('services.paypal.cancel_url'), $order);
    }

    protected function urlWithOrder(string $url, Order $order): string
    {
        $separator = Str::contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query([
            'order_id' => $order->id,
            'order_number' => $order->number,
        ]);
    }

    protected function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
