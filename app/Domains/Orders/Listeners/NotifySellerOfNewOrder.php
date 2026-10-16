<?php

namespace App\Domains\Orders\Listeners;

use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Notifications\NewOrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Runs on the queue: a slow mail server must never stand between a buyer
 * submitting an order and the seller seeing it.
 */
class NotifySellerOfNewOrder implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('items');

        $seller = $order->seller;

        if (! $seller) {
            return;
        }

        $seller->notify(new NewOrderNotification($order));
    }
}
