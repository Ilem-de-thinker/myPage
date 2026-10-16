<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Payments\Jobs\ProcessPaymentWebhook;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Payments\Services\Gateways\FlutterwaveGateway;
use App\Domains\Payments\Services\Gateways\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Receives gateway callbacks. Every delivery is authenticated before it is stored,
 * deduplicated on the gateway's event id, and handed to a queued job so the
 * gateway gets its 200 immediately.
 */
class PaymentWebhookController
{
    public function paystack(Request $request, PaystackGateway $gateway): Response
    {
        $this->assertValidSignature($request, $gateway->sign($request->getContent()), (string) $request->header('x-paystack-signature'));

        $this->storeAndDispatch('paystack', $request->json()->all(), $request->json('data.id'));

        return $this->acknowledge();
    }

    public function flutterwave(Request $request, FlutterwaveGateway $gateway): Response
    {
        $this->assertValidSignature($request, $gateway->sign($request->getContent()), (string) $request->header('verif-hash'));

        $this->storeAndDispatch('flutterwave', $request->json()->all(), $request->json('data.id') ?? $request->json('event_id'));

        return $this->acknowledge();
    }

    /**
     * Tell the gateway the delivery landed.
     *
     * Exactly 200, and that is not a stylistic choice. Both gateways treat
     * anything else as a failed attempt: they retry the event for up to 72 hours
     * and then stop, so an order whose only settling path is this webhook is
     * silently never confirmed. 204 reads as "received" to a human and to a
     * browser, which is precisely why it is easy to reach for here by mistake.
     *
     * Everything slow already happens off this request, in a queued job.
     */
    private function acknowledge(): Response
    {
        return response()->json(['received' => true]);
    }

    private function assertValidSignature(Request $request, string $expected, string $provided): void
    {
        abort_unless($provided !== '' && hash_equals($expected, $provided), 401);
    }

    /**
     * A replayed delivery is a no-op rather than an error.
     */
    private function storeAndDispatch(string $gateway, array $payload, mixed $eventId): void
    {
        $event = WebhookEvent::firstOrCreate(
            ['gateway' => $gateway, 'event_id' => (string) ($eventId ?: Str::uuid())],
            ['payload' => $payload],
        );

        if ($event->wasRecentlyCreated) {
            try {
                ProcessPaymentWebhook::dispatch($event->getKey());
            } catch (Throwable) {
                // A queue outage must not make the gateway retry an already-stored
                // event forever; the row stays unprocessed for the nightly sweep.
            }
        }
    }
}
