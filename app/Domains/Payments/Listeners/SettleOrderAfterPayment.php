<?php

namespace App\Domains\Payments\Listeners;

use App\Domains\Catalog\Actions\AdjustStock;
use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Events\PaymentConfirmed;
use Illuminate\Support\Facades\DB;

class SettleOrderAfterPayment
{
    public function __construct(
        private readonly UpdateOrderStatus $updateOrderStatus,
        private readonly AdjustStock $adjustStock,
    ) {}

    public function handle(PaymentConfirmed $event): void
    {
        $payable = $event->transaction->payable;

        if (! $payable instanceof Order) {
            return;
        }

        if ($payable->status === OrderStatus::Paid) {
            return;
        }

        $this->updateOrderStatus->handle($payable, OrderStatus::Paid, note: "Payment {$event->transaction->reference}");

        DB::transaction(function () use ($payable) {
            foreach ($payable->items()->with(['product', 'variant'])->get() as $item) {
                $this->adjustStock->handle(
                    $item->product,
                    -$item->quantity,
                    'order_paid',
                    $payable,
                    $item->variant,
                );
            }
        });
    }
}
