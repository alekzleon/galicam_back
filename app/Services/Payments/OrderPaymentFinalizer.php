<?php

namespace App\Services\Payments;

use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Services\Orders\OrderNotificationService;
use Illuminate\Support\Facades\DB;

class OrderPaymentFinalizer
{
    public function __construct(
        protected OrderNotificationService $orderNotificationService
    ) {
    }

    public function validateOrderStock(Order $order): void
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || $product->stock === null) {
                continue;
            }

            abort_if((float) $product->stock <= 0, 422, "El producto {$item->name_snapshot} ya no tiene inventario disponible.");
            abort_if((float) $product->stock < (float) $item->quantity, 422, "El producto {$item->name_snapshot} solo tiene {$product->stock} pieza(s) disponibles.");
        }
    }

    public function markPaid(Order $order, string $provider, array $fields = []): void
    {
        if ($order->payment_status !== Order::PAYMENT_PAID) {
            $order->forceFill(array_merge([
                'status' => Order::STATUS_PAID,
                'payment_status' => Order::PAYMENT_PAID,
                'payment_method' => $provider,
                'paid_at' => now(),
            ], $fields))->save();

            $this->deductOrderStock($order->fresh(['items.product']));
            $this->activateCashbackTransactions($order);
            $this->orderNotificationService->sendPurchaseNotifications(
                $order->fresh(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress', 'items', 'payments'])
            );

            return;
        }

        if ($fields !== []) {
            $order->forceFill($fields)->save();
        }

        $this->deductOrderStock($order->fresh(['items.product']));
        $this->activateCashbackTransactions($order);
    }

    public function markFailed(Order $order, string $provider, array $fields = []): void
    {
        if ($order->payment_status === Order::PAYMENT_PAID) {
            return;
        }

        $order->forceFill(array_merge([
            'status' => Order::STATUS_PAYMENT_FAILED,
            'payment_status' => Order::PAYMENT_FAILED,
            'payment_method' => $provider,
        ], $fields))->save();
    }

    protected function activateCashbackTransactions(Order $order): void
    {
        CashbackTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', CashbackTransaction::STATUS_PENDING)
            ->update(['status' => CashbackTransaction::STATUS_AVAILABLE]);
    }

    protected function deductOrderStock(Order $order): void
    {
        $metadata = $order->metadata ?? [];

        if (data_get($metadata, 'stock_deducted_at')) {
            return;
        }

        DB::transaction(function () use ($order, $metadata) {
            $deducted = [];

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $product || $product->stock === null) {
                    continue;
                }

                $before = (float) $product->stock;
                $quantity = (float) $item->quantity;
                $after = max(0, round($before - $quantity, 2));

                $product->forceFill(['stock' => $after])->save();

                $deducted[] = [
                    'product_id' => $product->id,
                    'sku' => $item->sku_snapshot,
                    'quantity' => $quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                ];
            }

            $metadata['stock_deducted_at'] = now()->toDateTimeString();
            $metadata['stock_deductions'] = $deducted;

            $order->forceFill(['metadata' => $metadata])->save();
        });
    }
}
