<?php

namespace App\Domains\Billing\Jobs;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Winds subscriptions down once their grace period is spent.
 *
 * This records the truth in the database, but it is not the gate: access is
 * resolved from dates on every read, so a delayed job can never keep a lapsed
 * merchant working. An operator override is respected and never expired early.
 */
class ExpireSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $now = now();

        Subscription::where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->where(fn ($query) => $query
                ->whereNull('grace_override_until')
                ->orWhere('grace_override_until', '<=', $now))
            ->update(['status' => SubscriptionStatus::Expired->value]);

        // A cancelled subscription that was still running when they cancelled is
        // finished once the period they paid for is over.
        Subscription::where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('cancelled_at')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', $now)
            ->where(fn ($query) => $query
                ->whereNull('grace_override_until')
                ->orWhere('grace_override_until', '<=', $now))
            ->update(['status' => SubscriptionStatus::Expired->value]);

        // A trial has no grace: once its last day is over it is finished. The
        // resolver already answers this from the date, so this only keeps the
        // stored status in step with that truth.
        Subscription::where('status', SubscriptionStatus::Trialing->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->where(fn ($query) => $query
                ->whereNull('grace_override_until')
                ->orWhere('grace_override_until', '<=', $now))
            ->update(['status' => SubscriptionStatus::Expired->value]);
    }
}
