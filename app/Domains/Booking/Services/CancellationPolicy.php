<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Seller\Models\StorePage;

/**
 * How much notice a seller needs before a booking can be changed or dropped.
 *
 * The window is stated on the booking screen rather than applied silently,
 * because a customer turned away at the door is worse than an empty slot.
 */
class CancellationPolicy
{
    public function windowHoursFor(?StorePage $page): int
    {
        return (int) ($page?->settings['cancellation_window_hours']
            ?? config('mypage.booking_cancellation_window_hours', 24));
    }

    /**
     * Hours from now until the appointment. Negative once it has started.
     */
    public function hoursUntil(Booking $booking): int
    {
        return (int) ceil(now()->diffInHours($booking->starts_at, absolute: false));
    }

    /**
     * True when the appointment is too close (or already under way) to change
     * without talking to the customer.
     */
    public function isLocked(Booking $booking): bool
    {
        return $this->hoursUntil($booking) < $this->windowHoursFor($booking->storePage);
    }

    public function hoursUntilLock(Booking $booking): int
    {
        return $this->windowHoursFor($booking->storePage) - $this->hoursUntil($booking);
    }
}
