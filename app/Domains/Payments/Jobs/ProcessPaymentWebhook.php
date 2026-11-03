<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Billing\Actions\ConfirmSubscriptionPayment;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Actions\SyncGatewaySubscription;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Payments\Services\GatewayFactory;
use App\Shared\Support\GatewayWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Events that describe a subscription rather than a single transaction, and
     * so carry no transaction reference to confirm.
     */
    private const SUBSCRIPTION_EVENTS = [
        GatewayWebhookEvent::SUBSCRIPTION_CREATE,
        GatewayWebhookEvent::SUBSCRIPTION_NOT_RENEW,
        GatewayWebhookEvent::SUBSCRIPTION_DISABLE,
        GatewayWebhookEvent::SUBSCRIPTION_EXPIRING_CARDS,
        GatewayWebhookEvent::INVOICE_PAYMENT_FAILED,
        GatewayWebhookEvent::INVOICE_UPDATE,
    ];

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(
        ConfirmTransaction $confirmTransaction,
        ConfirmSubscriptionPayment $confirmSubscriptionPayment,
        SyncGatewaySubscription $syncSubscription,
        GatewayFactory $gateways,
        SubscriptionManager $subscriptions,
    ): void {
        $event = WebhookEvent::findOrFail($this->webhookEventId);
        $payload = $event->payload;
        $name = (string) ($payload['event'] ?? '');

        if (in_array($name, self::SUBSCRIPTION_EVENTS, true)) {
            $syncSubscription->handle($name, $payload);
        } elseif ($reference = $this->referenceFrom($payload)) {
            $transaction = Transaction::withoutGlobalScopes()->where('reference', $reference)->first();

            if ($transaction === null) {
                // A renewal charge: the gateway bills on its own schedule and
                // no local Transaction exists for it. Reconcile through the
                // subscription code rather than fail the job forever.
                $this->renewSubscription($payload, $reference, $gateways, $subscriptions);
            } elseif ($transaction->payable_type === 'subscription') {
                $confirmSubscriptionPayment->handle($reference);
            } else {
                $confirmTransaction->handle($reference);
            }
        }

        $event->forceFill(['processed_at' => now()])->save();
    }

    /**
     * Pull the transaction reference out of a gateway payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function referenceFrom(array $payload): ?string
    {
        $data = $payload['data'] ?? [];

        $reference = $data['reference']
            ?? $data['tx_ref']
            ?? $data['transaction']['reference']
            ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * Roll the period forward for a renewal charge that has no local
     * transaction. Requires the gateway's own re-verification — the signature
     * on the webhook is authentic, but the money movement is re-checked here —
     * and an NGN currency. The amount is deliberately not compared to the
     * plan price: an admin may have repriced the plan, and a paid renewal
     * must never be dropped on a stale comparison.
     *
     * @param  array<string, mixed>  $payload
     */
    private function renewSubscription(array $payload, string $reference, GatewayFactory $gateways, SubscriptionManager $subscriptions): void
    {
        $data = $payload['data'] ?? [];

        $code = $data['subscription']['subscription_code']
            ?? $data['subscription_code']
            ?? null;

        if (! is_string($code) || $code === '') {
            return;
        }

        $subscription = Subscription::where('gateway_subscription_code', $code)->first();

        if ($subscription === null) {
            return;
        }

        $verification = $gateways->platform()->verify($reference);

        if (($verification['status'] ?? null) !== 'success' || ($verification['currency'] ?? null) !== 'NGN') {
            return;
        }

        $subscriptions->renew($subscription, null, $reference);
    }
}
