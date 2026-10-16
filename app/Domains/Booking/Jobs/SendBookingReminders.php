<?php

namespace App\Domains\Booking\Jobs;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Notifications\BookingReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Nudges buyers ahead of an appointment, once per lead time.
 *
 * A window rather than an exact time, because a scheduler that missed 11:00
 * would otherwise silently skip the 24h reminder for that day entirely.
 */
class SendBookingReminders implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $hoursBefore = 24, public readonly int $windowMinutes = 30) {}

    public function handle(): void
    {
        $now = now();

        // The window is centred on the lead time, not on now: a booking exactly
        // 24 hours out is the one the 24h reminder is for.
        $target = $now->copy()->addHours($this->hoursBefore);

        Booking::withoutGlobalScopes()
            ->whereIn('status', BookingStatus::openStatuses())
            ->whereNotNull('customer_email')
            ->whereNull($this->column())
            ->whereBetween('starts_at', [
                $target->copy()->subMinutes($this->windowMinutes),
                $target->copy()->addMinutes($this->windowMinutes),
            ])
            ->with(['service', 'staffMember', 'storePage'])
            ->chunkById(50, function ($bookings) use ($now) {
                foreach ($bookings as $booking) {
                    Notification::route('mail', $booking->customer_email)
                        ->notify(new BookingReminder($booking, $this->hoursBefore));

                    // Stamped before sending so a mail failure does not become a
                    // retry storm that spams the buyer.
                    $booking->forceFill([$this->column() => $now])->save();
                }
            });
    }

    private function column(): string
    {
        return $this->hoursBefore >= 24 ? 'reminded_24h_at' : 'reminded_2h_at';
    }
}
