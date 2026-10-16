<?php

namespace App\Domains\Billing\Jobs;

use App\Domains\Billing\Actions\EscrowService;
use App\Domains\Billing\Models\Escrow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Releases escrow the buyer never complained about.
 *
 * The whole point of a deadline: without it a buyer's silence would hold a
 * seller's money forever.
 */
class ReleaseAutoEscrow implements ShouldQueue
{
    use Queueable;

    /**
     * EscrowService is resolved by the container rather than injected, because
     * the scheduler builds this job itself with no arguments.
     */
    public function handle(EscrowService $escrowService): void
    {
        Escrow::dueForAutoRelease()->chunkById(50, function ($escrows) use ($escrowService) {
            foreach ($escrows as $held) {
                $escrowService->release($held, 'escrow_auto_release');
            }
        });
    }
}
