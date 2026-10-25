<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Payments\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-dispatches webhook rows accepted by the HTTP endpoint whose queue job did
 * not finish. The payload still carries the transaction reference, so
 * ProcessPaymentWebhook resolves the seller's gateway again and never falls
 * back from a seller account to the platform account.
 */
class SweepUnprocessedWebhooks implements ShouldQueue
{
    use Queueable;

    private const BATCH_SIZE = 500;

    public function handle(): void
    {
        WebhookEvent::query()
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->pluck('id')
            ->each(function (int $id): void {
                ProcessPaymentWebhook::dispatch($id);
            });
    }
}
