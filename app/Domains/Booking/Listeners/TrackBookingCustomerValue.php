<?php

namespace App\Domains\Booking\Listeners;

use App\Domains\Booking\Events\BookingStatusChanged;
use App\Domains\Booking\Notifications\BookingStatusChangedNotification;
use App\Domains\Customers\Actions\UpsertCustomerFromBooking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Keeps the customer book's money column honest as an appointment moves in and
 * out of the states where the seller actually holds the deposit.
 */
class TrackBookingCustomerValue implements ShouldQueue
{
    public function __construct(private readonly UpsertCustomerFromBooking $upsertCustomerFromBooking) {}

    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking->loadMissing(['service', 'storePage']);

        $this->upsertCustomerFromBooking->applyStatusChange($booking, $event->from, $event->to);

        if ($event->to->notifiesBuyer() && $booking->customer_email) {
            Notification::route('mail', $booking->customer_email)
                ->notify(new BookingStatusChangedNotification($booking, $event->to->label()));
        }
    }
}
