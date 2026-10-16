<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Events\BookingStatusChanged;
use App\Domains\Booking\Exceptions\InvalidTransition;
use App\Domains\Booking\Models\Booking;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\DB;

class UpdateBookingStatus
{
    /**
     * The only way a booking changes state. Validates the move, writes history
     * and fires the event, exactly as the order side does.
     */
    public function handle(Booking $booking, BookingStatus $to, ?Seller $by = null, ?string $note = null): Booking
    {
        if (! $booking->status->canMoveTo($to)) {
            throw new InvalidTransition($booking->status->value, $to->value);
        }

        $from = DB::transaction(function () use ($booking, $to, $by, $note) {
            $locked = Booking::withoutGlobalScopes()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            $locked->update(['status' => $to]);

            // A hold only exists while a payment is outstanding; confirming or
            // cancelling it clears the expiry so nothing later frees the slot.
            if ($to !== BookingStatus::PendingPayment) {
                $locked->forceFill(['expires_at' => null])->save();
            }

            $locked->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'by_seller_id' => $by?->getKey(),
                'note' => $note,
            ]);

            return $from;
        });

        $booking->refresh();

        event(new BookingStatusChanged($booking, $from, $to));

        return $booking;
    }
}
