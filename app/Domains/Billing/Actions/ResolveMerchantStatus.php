<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\MerchantStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\GracePeriod;
use Illuminate\Support\Carbon;

/**
 * The single authority for whether a merchant is active, in grace, expired or
 * cancelled.
 *
 * Stored statuses are the gateway's history. This reads them together with the
 * real dates, so access does not wait for a nightly job and an operator override
 * takes effect immediately. Grace always allows writes; everyone else does not.
 */
final class ResolveMerchantStatus
{
    public function __construct(private readonly GracePeriod $grace) {}

    public function handle(?Subscription $subscription): MerchantStatus
    {
        // No subscription row means this seller predates billing. Failing open
        // is deliberate: blocking somebody who was never charged is indefensible.
        if ($subscription === null) {
            return MerchantStatus::Active;
        }

        $graceEnds = $this->graceEndsAt($subscription);

        return match ($subscription->status) {
            SubscriptionStatus::Trialing => $this->trialStatus($subscription),
            SubscriptionStatus::Active => $this->activeStatus($subscription, $graceEnds),
            SubscriptionStatus::Pending => $this->pendingStatus($subscription),
            SubscriptionStatus::PastDue => $this->pastDueStatus($subscription, $graceEnds),
            SubscriptionStatus::Cancelled => $this->cancelledStatus($subscription, $graceEnds),
            SubscriptionStatus::Expired => $this->lapsedStatus($graceEnds, MerchantStatus::Expired),
        };
    }

    /**
     * The latest moment access is allowed to continue, combining the operator
     * override, the gateway's grace date, and the grace window derived from the
     * paid period that actually ran out.
     *
     * A trial derives no grace: its plan simply ends. Only an explicit operator
     * override can extend access past that.
     */
    public function graceEndsAt(Subscription $subscription): ?Carbon
    {
        $dates = array_filter([
            $subscription->grace_ends_at,
            $subscription->grace_override_until,
        ]);

        $ranOut = $subscription->status === SubscriptionStatus::Trialing
            ? null
            : $subscription->current_period_ends_at;

        if ($ranOut !== null) {
            $dates[] = $ranOut->copy()->addDays($this->grace->days());
        }

        if ($dates === []) {
            return null;
        }

        usort($dates, fn (Carbon $a, Carbon $b): int => $a->getTimestamp() <=> $b->getTimestamp());

        return end($dates);
    }

    private function trialStatus(Subscription $subscription): MerchantStatus
    {
        if ($subscription->trial_ends_at === null || $subscription->trial_ends_at->isFuture()) {
            return MerchantStatus::Active;
        }

        // A trial is its own plan and stops dead when its days run out. Grace is
        // for a payment that failed on a paid plan, not for the end of a trial.
        // An operator can still extend access explicitly.
        return $subscription->grace_override_until?->isFuture()
            ? MerchantStatus::Grace
            : MerchantStatus::Expired;
    }

    /**
     * Checkout has started but no money has arrived, so nothing is unlocked yet.
     */
    private function pendingStatus(Subscription $subscription): MerchantStatus
    {
        return $subscription->grace_override_until?->isFuture()
            ? MerchantStatus::Grace
            : MerchantStatus::Expired;
    }

    private function activeStatus(Subscription $subscription, ?Carbon $graceEnds): MerchantStatus
    {
        // No end date means a manually maintained subscription with no clock.
        if ($subscription->current_period_ends_at === null) {
            return MerchantStatus::Active;
        }

        if ($subscription->current_period_ends_at->isFuture()) {
            return MerchantStatus::Active;
        }

        if ($subscription->cancelled_at !== null) {
            return $this->cancelledLapsedStatus($subscription, MerchantStatus::Cancelled);
        }

        return $this->lapsedStatus($graceEnds, MerchantStatus::Expired);
    }

    private function pastDueStatus(Subscription $subscription, ?Carbon $graceEnds): MerchantStatus
    {
        // A failed renewal reported before the paid period ended is still paid
        // time. Grace only begins when that period is actually over.
        if ($subscription->current_period_ends_at?->isFuture()) {
            return MerchantStatus::Active;
        }

        return $this->lapsedStatus($graceEnds, MerchantStatus::Expired);
    }

    private function cancelledStatus(Subscription $subscription, ?Carbon $graceEnds): MerchantStatus
    {
        // Cancelling stops the next charge; it never takes back time already paid.
        if ($subscription->current_period_ends_at?->isFuture()) {
            return MerchantStatus::Active;
        }

        return $this->cancelledLapsedStatus($subscription, MerchantStatus::Cancelled);
    }

    /**
     * A cancellation is a choice, not a failed payment, so it gets no derived
     * grace. An explicit operator override still applies.
     */
    private function cancelledLapsedStatus(Subscription $subscription, MerchantStatus $fallback): MerchantStatus
    {
        return $subscription->grace_override_until?->isFuture() ? MerchantStatus::Grace : $fallback;
    }

    private function lapsedStatus(?Carbon $graceEnds, MerchantStatus $fallback): MerchantStatus
    {
        return $graceEnds?->isFuture() ? MerchantStatus::Grace : $fallback;
    }
}
