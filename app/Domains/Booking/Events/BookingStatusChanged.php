<?php

namespace App\Domains\Booking\Events;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Both statuses travel together because listeners need to know what the booking
 * counted as before, not just what it counts as now.
 */
class BookingStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly BookingStatus $from,
        public readonly BookingStatus $to,
    ) {}
}
