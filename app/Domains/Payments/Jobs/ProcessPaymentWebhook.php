<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Billing\Actions\SyncGatewaySubscription;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Models\WebhookEvent;
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

    public function handle(ConfirmTransaction $confirmTransaction, SyncGatewaySubscription $syncSubscription): void
    {
        $event = WebhookEvent::findOrFail($this->webhookEventId);
        $payload = $event->payload;
        $name = (string) ($payload['event'] ?? '');

        if (in_array($name, self::SUBSCRIPTION_EVENTS, true)) {
            $syncSubscription->handle($name, $payload);
        } elseif ($reference = $this->referenceFrom($payload)) {
            $confirmTransaction->handle($reference);
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
}
