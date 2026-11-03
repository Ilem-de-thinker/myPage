<?php

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Ai\Services\AiUsageSummary;
use App\Domains\Billing\Actions\ConfirmSubscriptionPayment;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\ManualTransferSubmitted;
use App\Domains\Billing\Notifications\PlanGatewayMismatch;
use App\Domains\Billing\Services\GracePeriod;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Seller\Models\Seller;
use App\Shared\Support\CoreAdmins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;

/**
 * Where a seller sees what they pay, when it renews, and how to change it.
 */
class BillingController extends Controller
{
    public function index(
        Request $request,
        SubscriptionAvailability $availability,
        GracePeriod $grace,
        AiUsageSummary $usage,
    ): View {
        $seller = $request->user();
        $page = $seller->storePages()->first();

        $subscription = Subscription::with('plan')
            ->where('seller_id', $seller->getKey())
            ->first();

        return view('dashboard.billing.index', [
            'page' => $page,
            'aiUsage' => $usage->forPage($page),
            'subscription' => $subscription,
            'plans' => Plan::active()
                ->where('slug', '!=', config('billing.trial_plan'))
                ->orderByDesc('is_featured')
                ->orderBy('price_kobo')
                ->get(),
            'history' => $subscription ? $this->historyFor($subscription) : new LengthAwarePaginator([], 0, 20),
            'transfers' => config('billing.manual_transfers'),
            'graceDays' => $grace->days(),
            'subscriptionsEnabled' => $availability->enabled(),
        ]);
    }

    public function subscribe(
        Request $request,
        SubscriptionManager $subscriptions,
        InitializePayment $initializePayment,
        SubscriptionAvailability $availability,
    ): RedirectResponse {
        abort_unless($availability->enabled(), 503, 'Subscriptions are temporarily disabled by the platform.');
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $plan = Plan::active()->where('slug', $data['plan'])->firstOrFail();

        // The trial is started automatically at signup, never bought.
        abort_if($plan->slug === config('billing.trial_plan'), 422, 'The trial plan cannot be purchased.');

        $mismatch = $this->planGatewayMismatch($plan, $this->gateways());

        if ($mismatch !== null) {
            CoreAdmins::send(new PlanGatewayMismatch($mismatch));

            return back()->withErrors([
                'plan' => 'This plan cannot be activated right now. The team has been notified and will fix it.',
            ]);
        }

        // Written as past_due, not active: only a confirmed payment makes it
        // live. A seller who abandons checkout is never marked paying.
        $subscription = $subscriptions->start(
            $request->user(),
            $plan,
            'gateway',
            null,
        );

        // The reference is minted inside InitializePayment, so the callback is a
        // bare URL and the gateway appends ?reference=... on return.
        //
        // Passing the plan is what makes this recurring rather than one more
        // period paid by hand: the gateway charges the plan's own amount and,
        // once it succeeds, keeps debiting that card on the plan's schedule. It
        // also means `plan` overrides the amount below, so the two must agree —
        // which is what keeps a stale plan from silently charging the wrong sum.
        $charge = $initializePayment->handle(
            $subscription,
            $data['email'] ?? $request->user()->email,
            route('billing.callback'),
            $plan->gateway_plan_code ? ['plan' => $plan->gateway_plan_code] : [],
        );

        return redirect()->away($charge);
    }

    /**
     * Where the gateway returns the seller. Like checkout, the query string is
     * never trusted: the charge is re-verified with the gateway first.
     */
    public function callback(
        Request $request,
        ConfirmSubscriptionPayment $confirmPayment,
    ): RedirectResponse {
        $reference = (string) $request->query('reference', '');

        $confirmed = $reference !== '' ? $confirmPayment->handle($reference) : null;

        return redirect()
            ->route('billing.index')
            ->with(
                'status',
                $confirmed !== null
                    ? 'Payment received. Thank you.'
                    : 'We could not confirm that payment. If you were charged, we will email you.'
            );
    }

    /**
     * Manual bank transfer, for sellers without a card. Admin approves.
     */
    public function requestManualSubscription(
        Request $request,
        SubscriptionManager $subscriptions,
        SubscriptionAvailability $availability,
    ): RedirectResponse {
        abort_unless($availability->enabled(), 503, 'Subscriptions are temporarily disabled by the platform.');
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'reference' => ['required', 'string', 'max:190'],
        ]);

        $plan = Plan::active()->where('slug', $data['plan'])->firstOrFail();

        abort_if($plan->slug === config('billing.trial_plan'), 422, 'The trial plan cannot be purchased.');

        $subscriptions->start($request->user(), $plan, 'manual', $data['reference']);

        CoreAdmins::send(new ManualTransferSubmitted([
            'seller_name' => $request->user()->name,
            'seller_email' => $request->user()->email,
            'plan_name' => $plan->name,
            'reference' => $data['reference'],
        ]));

        return redirect()
            ->route('billing.index')
            ->with('status', 'Thanks. We will confirm your transfer within one working day; your plan activates once it lands.');
    }

    /**
     * Stop a subscription.
     *
     * The local record alone is not the cancellation: while the gateway still holds
     * a live subscription it keeps taking the money, and the seller would discover
     * that on their statement rather than here. So the gateway is told first, and a
     * gateway we cannot reach is reported plainly instead of leaving a local
     * "cancelled" subscription that is still being charged.
     */
    public function cancel(
        Request $request,
        SubscriptionManager $subscriptions,
        PaymentGatewayInterface $gateway,
    ): RedirectResponse {
        $subscription = Subscription::where('seller_id', $request->user()->getKey())->firstOrFail();

        if ($subscription->gateway_subscription_code !== null) {
            try {
                $gateway->disableSubscription($subscription->gateway_subscription_code);
            } catch (Throwable $exception) {
                report($exception);

                return back()->withErrors([
                    'subscription' => 'We could not reach the payment provider, so your card may still be charged. Please try again in a moment.',
                ]);
            }
        }

        $subscriptions->cancel($subscription);

        return redirect()
            ->route('billing.index')
            ->with('status', 'Cancelled. You keep everything until the period you paid for ends.');
    }

    /**
     * Where the seller fixes a card the gateway could no longer charge.
     *
     * A hosted page, so no card data passes through this application at all.
     */
    public function manageCard(Request $request, PaymentGatewayInterface $gateway): RedirectResponse
    {
        $subscription = Subscription::where('seller_id', $request->user()->getKey())->firstOrFail();

        abort_if($subscription->gateway_subscription_code === null, 404);

        try {
            return redirect()->away($gateway->manageLink($subscription->gateway_subscription_code));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'subscription' => 'We could not reach the payment provider. Please try again in a moment.',
            ]);
        }
    }

    /**
     * A gateway plan the seller is about to be charged must agree with what we
     * quote, or their card is billed an amount they never saw. Returns the
     * mismatch to report, or null when they agree (or cannot be checked).
     */
    private function planGatewayMismatch(Plan $plan, PaymentGatewayInterface $gateway): ?array
    {
        if (! filled($plan->gateway_plan_code)) {
            return null;
        }

        try {
            $remote = $gateway->fetchPlan($plan->gateway_plan_code);
        } catch (Throwable $exception) {
            // The gateway is unreachable; refusing to sell at all over a
            // transient failure punishes every seller. Log and let it through —
            // the confirm step still re-verifies the actual charge.
            report($exception);

            return null;
        }

        $remoteAmount = $remote['amount_kobo'] ?? null;
        $remoteInterval = $remote['interval'] ?? null;

        if ($remoteAmount !== null && (int) $remoteAmount !== (int) $plan->price_kobo) {
            return ['plan_name' => $plan->name, 'local_kobo' => (int) $plan->price_kobo, 'gateway_kobo' => (int) $remoteAmount, 'gateway_plan_code' => $plan->gateway_plan_code];
        }

        if ($remoteInterval !== null && $remoteInterval !== $plan->interval) {
            return ['plan_name' => $plan->name, 'local_kobo' => (int) $plan->price_kobo, 'gateway_kobo' => (int) ($remoteAmount ?? 0), 'gateway_plan_code' => $plan->gateway_plan_code];
        }

        return null;
    }

    private function gateways(): PaymentGatewayInterface
    {
        return app(PaymentGatewayInterface::class);
    }

    private function historyFor(Subscription $subscription)
    {
        return Transaction::where('payable_type', 'subscription')
            ->where('payable_id', $subscription->getKey())
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }
}
