<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\SubscriptionGraceStarted;
use App\Domains\Billing\Services\GracePeriod;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Starts and renews a seller's subscription.
 *
 * Nothing here talks to a gateway directly: a caller either has a confirmed
 * gateway reference or is approving a manual transfer, and this only records
 * what was agreed. The recurring charges themselves are the gateway's job — it
 * debits the card on its own schedule and says so over the webhook — so this
 * class mirrors those events rather than scheduling them.
 */
class SubscriptionManager
{
    public function __construct(private readonly GracePeriod $grace) {}

    /**
     * Record the gateway's identity for a subscription, and the date it has
     * promised to charge next.
     *
     * Idempotent, because the gateway can announce the same subscription more
     * than once and a replayed webhook must not roll the billing date backwards.
     *
     * @param  array{subscription_code: string, customer_code?: ?string, email_token?: ?string, status?: string, next_payment_date?: ?string}  $attributes
     */
    public function attachGatewaySubscription(Subscription $subscription, array $attributes): Subscription
    {
        $subscription->forceFill(array_filter([
            'gateway_subscription_code' => $attributes['subscription_code'] ?? null,
            'gateway_customer_code' => $attributes['customer_code'] ?? null,
            'email_token' => $attributes['email_token'] ?? null,
            'next_payment_date' => $this->toDate($attributes['next_payment_date'] ?? null),
        ], fn ($value) => $value !== null))->save();

        return $subscription;
    }

    /**
     * Mirror a gateway subscription status onto our own.
     *
     * Paystack reports five: active, non-renewing, attention, completed and
     * cancelled. `attention` is the one that has no counterpart — the card was
     * declined but the subscription is still alive and will be retried, so it
     * becomes past_due with a grace period rather than an outright expiry.
     */
    public function applyGatewayStatus(Subscription $subscription, string $gatewayStatus): Subscription
    {
        match ($gatewayStatus) {
            'active' => $subscription->update([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
                'grace_ends_at' => null,
                'grace_override_until' => null,
            ]),
            'non-renewing' => $subscription->update(['cancelled_at' => now()]),
            'attention' => $this->markPastDue($subscription),
            'completed', 'cancelled' => $this->cancel($subscription),
            default => null,
        };

        return $subscription;
    }

    /**
     * @param  mixed  $value
     */
    private function toDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Begin a subscription.
     *
     * A trial starts immediately and grants access. A paid plan bought through
     * the gateway only grants access once its payment is confirmed: starting
     * checkout records the intent without changing what the seller can do. A
     * manual transfer is money already sent, so it starts paying immediately.
     */
    public function start(
        Seller $seller,
        Plan $plan,
        string $paymentMethod = 'trial',
        ?string $gatewayReference = null,
        ?Carbon $now = null,
    ): Subscription {
        $now ??= now();

        return DB::transaction(function () use ($seller, $plan, $paymentMethod, $gatewayReference, $now) {
            $existing = Subscription::query()
                ->where('seller_id', $seller->getKey())
                ->lockForUpdate()
                ->first();

            $trialDays = (int) ($plan->trial_days ?? 0);

            // A trial is either the dedicated trial plan being started at signup,
            // or a first-time card signup on a plan that still carries trial days.
            $onTrial = $paymentMethod === 'trial'
                || ($paymentMethod === 'gateway' && $existing === null && $trialDays > 0);

            // A seller who already has a subscription keeps exactly what they
            // have until the payment is confirmed. An abandoned checkout must
            // never silently change their access.
            $keepExisting = ! $onTrial && $existing !== null && $paymentMethod === 'gateway';

            $status = match (true) {
                $onTrial => SubscriptionStatus::Trialing,
                $keepExisting => $existing->status,
                $paymentMethod === 'gateway' => SubscriptionStatus::Pending,
                default => SubscriptionStatus::Active,
            };

            $withoutPeriod = $onTrial || $status === SubscriptionStatus::Pending;

            // A live subscription is replaced rather than duplicated: the unique
            // index on seller_id would fail otherwise.
            $subscription = Subscription::updateOrCreate(
                ['seller_id' => $seller->getKey()],
                [
                    'plan_id' => $plan->getKey(),
                    'status' => $status,
                    'price_kobo' => $plan->price_kobo,
                    'trial_ends_at' => $onTrial
                        ? $now->copy()->addDays($trialDays)
                        : ($keepExisting ? $existing->trial_ends_at : null),
                    'current_period_starts_at' => $withoutPeriod
                        ? null
                        : ($keepExisting ? $existing->current_period_starts_at : $now->copy()),
                    'current_period_ends_at' => $withoutPeriod
                        ? null
                        : ($keepExisting ? $existing->current_period_ends_at : $this->periodEnd($now, $plan->interval)),
                    'cancelled_at' => $keepExisting ? $existing->cancelled_at : null,
                    'grace_ends_at' => $keepExisting ? $existing->grace_ends_at : null,
                    'grace_override_until' => $keepExisting ? $existing->grace_override_until : null,
                    'payment_method' => $paymentMethod,
                    'gateway_reference' => $gatewayReference,
                ],
            );

            return $subscription;
        });
    }

    /**
     * Roll the current period forward after a confirmed payment.
     */
    public function renew(Subscription $subscription, ?Carbon $now = null): Subscription
    {
        $now ??= now();

        $periodStart = $subscription->current_period_ends_at?->isPast() === false
            ? $subscription->current_period_ends_at
            : $now;

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_starts_at' => $periodStart,
            'current_period_ends_at' => $this->periodEnd($periodStart, $subscription->plan?->interval ?? 'monthly'),
            'grace_ends_at' => null,
            'grace_override_until' => null,
        ]);

        return $subscription;
    }

    /**
     * A cancellation stops the next renewal; it never rewrites history, so the
     * seller keeps whatever they already paid for until the period ends.
     */
    public function cancel(Subscription $subscription, ?Carbon $now = null): Subscription
    {
        $now ??= now();

        $endsThisPeriod = $subscription->current_period_ends_at?->isFuture() ?? false;

        $subscription->update([
            'cancelled_at' => $now,
            'status' => $endsThisPeriod ? SubscriptionStatus::Active : SubscriptionStatus::Cancelled,
        ]);

        return $subscription;
    }

    /**
     * Called when payment fails. Starts the grace period rather than cutting the
     * seller off on the spot.
     */
    public function markPastDue(Subscription $subscription, ?Carbon $now = null): Subscription
    {
        $now ??= now();

        // Retries and repeated webhooks must not email the seller every time.
        $alreadyInGrace = $subscription->status === SubscriptionStatus::PastDue
            && $subscription->grace_ends_at?->isFuture();

        $subscription->update([
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => $this->grace->end($now),
        ]);

        if (! $alreadyInGrace && $this->grace->days() > 0) {
            $subscription->seller?->notify(new SubscriptionGraceStarted($subscription->grace_ends_at));
        }

        return $subscription;
    }

    /**
     * The end of a trial is not the end of a subscription: it becomes billable,
     * and only lapses later if nobody pays.
     */
    public function endTrial(Subscription $subscription, ?Carbon $now = null): Subscription
    {
        $now ??= now();

        $subscription->update([
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => $this->grace->end($now),
        ]);

        if ($this->grace->days() > 0) {
            $subscription->seller?->notify(new SubscriptionGraceStarted($subscription->grace_ends_at));
        }

        return $subscription;
    }

    private function periodEnd(Carbon $from, string $interval): Carbon
    {
        return match ($interval) {
            'yearly' => $from->copy()->addYear(),
            'weekly' => $from->copy()->addWeek(),
            default => $from->copy()->addMonth(),
        };
    }
}
