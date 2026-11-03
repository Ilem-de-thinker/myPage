<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Services\AiUsageReservations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A worker can be killed after reserving a credit but before it can release
 * one. Without this sweep, those pending rows would hold a merchant's monthly
 * allowance forever.
 */
class ReleaseStaleAiReservations implements ShouldQueue
{
    use Queueable;

    public function handle(AiUsageReservations $reservations): void
    {
        $reservations->releaseStale();
    }
}
