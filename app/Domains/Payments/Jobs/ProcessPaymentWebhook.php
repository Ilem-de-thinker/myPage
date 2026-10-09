<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(ConfirmTransaction $confirmTransaction): void
    {
        $event = WebhookEvent::findOrFail($this->webhookEventId);

        $reference = $this->referenceFrom($event);

        if ($reference !== null) {
            $confirmTransaction->handle($reference);
        }

        $event->forceFill(['processed_at' => now()])->save();
    }

    /**
     * Pull the transaction reference out of a gateway payload.
     */
    private function referenceFrom(WebhookEvent $event): ?string
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];

        $reference = $data['reference']
            ?? $data['tx_ref']
            ?? $payload['data']['transaction']['reference']
            ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
