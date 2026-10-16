<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Services\CustomerIdentity;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;

/**
 * Builds the seller's customer book from the orders buyers place. Nobody has to
 * maintain a list by hand.
 */
class UpsertCustomerFromOrder
{
    public function __construct(private readonly CustomerIdentity $identity) {}

    public function handle(Order $order): ?Customer
    {
        $resolved = $this->identity->resolve(
            $order->seller_id,
            $order->customer_phone,
            $order->customer_email,
            'order',
        );

        if ($resolved === null) {
            return null;
        }

        $customer = $resolved['customer'];

        // Last order wins, so a buyer who corrects their name at checkout sees
        // it reflected. A blank on a later order must not wipe what we know.
        if ($order->customer_name) {
            $customer->update(['name' => $order->customer_name]);
        }

        $order->forceFill(['customer_id' => $customer->getKey()])->save();

        return $customer;
    }

    /**
     * Move the lifetime value as an order moves in and out of the states where
     * the seller actually has the buyer's money.
     */
    public function applyStatusChange(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $this->applyLifetimeValue(
            $order->customer_id,
            $from->countsTowardsLifetimeValue(),
            $to->countsTowardsLifetimeValue(),
            $order->total_kobo,
        );
    }

    /**
     * Shared with the booking side so a refund is handled the same way in both.
     */
    public function applyLifetimeValue(?int $customerId, bool $wasCounting, bool $willCount, int $amountKobo): void
    {
        $delta = match (true) {
            $wasCounting && ! $willCount => -$amountKobo,
            ! $wasCounting && $willCount => $amountKobo,
            default => 0,
        };

        if ($delta === 0 || $customerId === null) {
            return;
        }

        $query = Customer::withoutGlobalScopes()->whereKey($customerId);

        $delta > 0
            ? $query->increment('lifetime_value_kobo', $delta)
            : $query->where('lifetime_value_kobo', '>=', abs($delta))->decrement('lifetime_value_kobo', abs($delta));
    }
}
