<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Seller\Models\StorePage;

/**
 * Row locking for the booking schedule, shared by CreateBooking and
 * RescheduleBooking so both serialise on the same row.
 */
final class StorePageForBooking
{
    public static function lock(int $storePageId): StorePage
    {
        return StorePage::withoutGlobalScopes()
            ->whereKey($storePageId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
