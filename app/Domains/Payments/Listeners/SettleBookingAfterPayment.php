<?php

namespace App\Domains\Payments\Listeners;

use App\Domains\Booking\Actions\UpdateBookingStatus;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payments\Events\PaymentConfirmed;

/**
 * A paid deposit holds the slot: the moment the gateway confirms, the booking
 * is a real booking. Before this listener nothing confirmed it, so a buyer
 * could pay and watch the hold expire at the 15-minute mark.
 */
class SettleBookingAfterPayment
{
    public function __construct(
        private readonly UpdateBookingStatus $updateBookingStatus,
    ) {}

    public function handle(PaymentConfirmed $event): void
    {
        $payable = $event->transaction->payable;

        if (! $payable instanceof Booking) {
            return;
        }

        // Idempotent: a replayed webhook or a second confirmation attempt
        // against an already-confirmed booking is a no-op.
        if ($payable->status !== BookingStatus::PendingPayment) {
            return;
        }

        $this->updateBookingStatus->handle(
            $payable,
            BookingStatus::Confirmed,
            note: "Payment {$event->transaction->reference}",
        );
    }
}
