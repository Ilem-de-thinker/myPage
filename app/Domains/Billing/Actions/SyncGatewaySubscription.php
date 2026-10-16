<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Subscription;
use App\Shared\Support\GatewayWebhookEvent;

/**
 * Mirrors a recurring billing lifecycle reported by the gateway.
 *
 * Paystack charges the card itself, on its own schedule. These events are the
 * only way we learn what it decided, so they — not a scheduler of our own — are
 * what moves a subscription between live, lapsing and gone.
 */
class SyncGatewaySubscription
{
    public function __construct(private readonly SubscriptionManager $subscriptions) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $data = $payload['data'] ?? [];

        // A subscription event names its subscription; an invoice event nests it.
        $code = $data['subscription_code']
            ?? $data['subscription']['subscription_code']
            ?? null;

        if (! is_string($code) || $code === '') {
            return;
        }

        $subscription = Subscription::where('gateway_subscription_code', $code)->first();

        // Nothing to do for a subscription we never created, and not an error:
        // the gateway keeps billing a plan this application no longer sells.
        if ($subscription === null) {
            return;
        }

        match ($event) {
            GatewayWebhookEvent::SUBSCRIPTION_CREATE => $this->subscriptions
                ->attachGatewaySubscription($subscription, $this->subscriptionAttributes($data)),
            GatewayWebhookEvent::SUBSCRIPTION_NOT_RENEW, GatewayWebhookEvent::SUBSCRIPTION_DISABLE => $this->subscriptions->applyGatewayStatus($subscription, $this->statusFor($event, $data)),
            GatewayWebhookEvent::INVOICE_PAYMENT_FAILED => $this->subscriptions->markPastDue($subscription),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subscription_code: string, customer_code?: ?string, email_token?: ?string, status?: string, next_payment_date?: ?string}
     */
    private function subscriptionAttributes(array $data): array
    {
        return [
            'subscription_code' => (string) $data['subscription_code'],
            'customer_code' => $data['customer']['customer_code'] ?? null,
            'email_token' => $data['email_token'] ?? null,
            'status' => isset($data['status']) ? (string) $data['status'] : null,
            'next_payment_date' => $data['next_payment_date'] ?? null,
        ];
    }

    /**
     * A disabled subscription reports `complete` when it simply ran out of
     * cycles and `cancelled` when somebody stopped it; both end it here.
     *
     * @param  array<string, mixed>  $data
     */
    private function statusFor(string $event, array $data): string
    {
        if ($event === GatewayWebhookEvent::SUBSCRIPTION_DISABLE) {
            return isset($data['status']) && $data['status'] === 'complete' ? 'completed' : 'cancelled';
        }

        return 'non-renewing';
    }
}
