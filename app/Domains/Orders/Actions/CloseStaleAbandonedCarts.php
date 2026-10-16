<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Orders\Models\AbandonedCart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Retires carts nobody came back for, so the seller's list keeps showing the
 * ones still worth a phone call rather than an ever-growing pile.
 */
class CloseStaleAbandonedCarts implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $olderThanDays = 30) {}

    public function handle(): void
    {
        AbandonedCart::withoutGlobalScopes()
            ->open()
            ->where('created_at', '<=', now()->subDays($this->olderThanDays))
            ->update(['status' => AbandonedCartStatus::Expired]);
    }
}
