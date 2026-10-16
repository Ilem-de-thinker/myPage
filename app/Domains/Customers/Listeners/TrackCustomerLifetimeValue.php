<?php

namespace App\Domains\Customers\Listeners;

use App\Domains\Customers\Actions\UpsertCustomerFromOrder;
use App\Domains\Orders\Events\OrderStatusChanged;

/**
 * Keeps the customer book honest as money moves: an order that reaches Paid adds
 * to the buyer's lifetime value, and one that is later refunded or cancelled
 * takes it back out.
 */
class TrackCustomerLifetimeValue
{
    public function __construct(private readonly UpsertCustomerFromOrder $upsertCustomerFromOrder) {}

    public function handle(OrderStatusChanged $event): void
    {
        $this->upsertCustomerFromOrder->applyStatusChange($event->order, $event->from, $event->to);
    }
}
