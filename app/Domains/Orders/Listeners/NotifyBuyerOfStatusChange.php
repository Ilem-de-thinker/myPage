<?php

namespace App\Domains\Orders\Listeners;

use App\Domains\Orders\Events\OrderStatusChanged;
use App\Domains\Orders\Notifications\OrderStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a buyer their order moved. Transactional rather than marketing, so it
 * is not gated on broadcast consent — only on the buyer having given an email.
 */
class NotifyBuyerOfStatusChange implements ShouldQueue
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing('storePage');

        if (! $event->to->notifiesBuyer() || ! $order->customer_email) {
            return;
        }

        Notification::route('mail', $order->customer_email)
            ->notify(new OrderStatusChangedNotification($order, $event->to->label()));
    }
}
