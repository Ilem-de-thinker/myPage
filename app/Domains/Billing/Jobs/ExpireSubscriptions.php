<?php

namespace App\Domains\Billing\Jobs;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Winds subscriptions down once their grace period is spent.
 *
 * Nothing here takes a shop offline; it only records the truth in the database.
 * The storefront is deliberately not gated, so a seller who misses a payment is
 * still trading while they sort it out.
 */
class ExpireSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Subscription::where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        // A cancelled subscription that was still running when they cancelled is
        // finished once the period they paid for is over.
        Subscription::where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('cancelled_at')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        // A trial that ran out with nobody signing up becomes expired too, but
        // only once its own trial window has closed.
        Subscription::where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now()->subDays((int) config('billing.grace_days', 7)))
            ->update(['status' => SubscriptionStatus::Expired->value]);
    }
}
