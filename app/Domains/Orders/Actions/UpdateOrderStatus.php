<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Events\OrderStatusChanged;
use App\Domains\Orders\Exceptions\InvalidTransition;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatus
{
    public function handle(Order $order, OrderStatus $to, ?Seller $by = null, ?string $note = null): Order
    {
        if (! $order->status->canMoveTo($to)) {
            throw new InvalidTransition($order->status, $to);
        }

        DB::transaction(function () use ($order, $to, $by, $note) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            $locked->update(['status' => $to]);

            $locked->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'by_seller_id' => $by?->getKey(),
                'note' => $note,
            ]);
        });

        event(new OrderStatusChanged($order->fresh(), $to));

        return $order->fresh();
    }
}
