<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Counts one view of a service's detail page.
 *
 * The same reasoning as RecordProductView, on the booking side: "Most booked"
 * needs a tiebreaker for a service nobody has booked yet, and this is it.
 */
class RecordServiceView
{
    public function handle(Service $service): void
    {
        DB::table('services')
            ->where('id', $service->getKey())
            ->increment('view_count');
    }
}
