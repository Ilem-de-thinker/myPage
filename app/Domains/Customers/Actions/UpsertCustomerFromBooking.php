<?php

namespace App\Domains\Customers\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Services\CustomerIdentity;

/**
 * The booking counterpart of UpsertCustomerFromOrder. Both share one identity
 * rule, so a client who books and then buys is still one row in the book.
 */
class UpsertCustomerFromBooking
{
    public function __construct(
        private readonly CustomerIdentity $identity,
        private readonly UpsertCustomerFromOrder $upsertCustomerFromOrder,
    ) {}

    public function handle(Booking $booking): ?Customer
    {
        $resolved = $this->identity->resolve(
            $booking->seller_id,
            $booking->customer_phone,
            $booking->customer_email,
            'booking',
        );

        if ($resolved === null) {
            return null;
        }

        $customer = $resolved['customer'];

        if ($booking->customer_name) {
            $customer->update(['name' => $booking->customer_name]);
        }

        $booking->forceFill(['customer_id' => $customer->getKey()])->save();

        return $customer;
    }

    /**
     * Only the deposit is money the seller actually holds at confirmation; the
     * rest turns on when the appointment is completed.
     */
    public function applyStatusChange(Booking $booking, BookingStatus $from, BookingStatus $to): void
    {
        $this->upsertCustomerFromOrder->applyLifetimeValue(
            $booking->customer_id,
            $from->countsTowardsLifetimeValue(),
            $to->countsTowardsLifetimeValue(),
            $booking->deposit_kobo ?: $booking->price_kobo,
        );
    }
}
