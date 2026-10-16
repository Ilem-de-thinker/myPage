<?php

namespace App\Domains\Booking\Jobs;

use App\Domains\Booking\Actions\UpdateBookingStatus;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Exceptions\InvalidTransition;
use App\Domains\Booking\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Frees slots held by bookings the buyer never paid for. Runs every minute:
 * a hold is only fifteen minutes long, so an hourly sweep would strand slots for
 * most of an hour.
 */
class ExpirePendingBookings implements ShouldQueue
{
    use Queueable;

    /**
     * UpdateBookingStatus is resolved from the container rather than injected,
     * because the scheduler builds this job itself with no arguments.
     */
    public function handle(UpdateBookingStatus $updateBookingStatus): void
    {
        Booking::withoutGlobalScopes()
            ->where('status', BookingStatus::PendingPayment->value)
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($bookings) use ($updateBookingStatus) {
                foreach ($bookings as $booking) {
                    // Guarded because another worker may have got there first;
                    // UpdateBookingStatus throws rather than double-writing.
                    try {
                        $updateBookingStatus->handle($booking, BookingStatus::Expired, note: 'Payment window closed');
                    } catch (InvalidTransition) {
                        continue;
                    }
                }
            });
    }
}
