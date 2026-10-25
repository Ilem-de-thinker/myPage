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
        /*
         * An on-platform order becomes paid because the gateway says so, never
         * because someone clicked a button. Cash or transfer that never reached
         * the seller's account would otherwise be indistinguishable from a
         * settled checkout, and the buyer would be told it was paid.
         */
        if ($by !== null && $order->status === OrderStatus::PendingPayment && $to === OrderStatus::Paid) {
            throw new InvalidTransition($order->status, $to);
        }

        if (! $order->status->canMoveTo($to)) {
            throw new InvalidTransition($order->status, $to);
        }

        $from = DB::transaction(function () use ($order, $to, $by, $note) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            $locked->update(['status' => $to]);

            $locked->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'by_seller_id' => $by?->getKey(),
                'note' => $note,
            ]);

            return $from;
        });

        $order->refresh();

        event(new OrderStatusChanged($order, $from, $to));

        return $order;
    }
}
