<?php

namespace App\Domains\Orders\Jobs;

use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Orders\Notifications\AbandonedCartReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Nudges buyers who started a checkout and walked away. One reminder only:
 * reminded_at is stamped so a cart left for a month is not mailed monthly.
 */
class RemindAbandonedCarts implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?int $olderThanHours = null) {}

    public function handle(): void
    {
        $cutoff = now()->subHours($this->olderThanHours ?? (int) config('mypage.abandoned_cart_reminder_hours', 24));

        AbandonedCart::withoutGlobalScopes()
            ->open()
            ->whereNotNull('email')
            ->where('created_at', '<=', $cutoff)
            ->whereNull('reminded_at')
            ->chunkById(100, function ($carts) {
                foreach ($carts as $cart) {
                    $cart->loadMissing('storePage');

                    Notification::route('mail', $cart->email)
                        ->notify(new AbandonedCartReminder($cart));

                    // Stamped before the send so a mail failure does not turn into
                    // a retry storm.
                    $cart->forceFill(['reminded_at' => now()])->save();
                }
            });
    }
}
