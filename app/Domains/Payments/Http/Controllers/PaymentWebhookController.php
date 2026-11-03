<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Payments\Exceptions\GatewayNotConfigured;
use App\Domains\Payments\Jobs\ProcessPaymentWebhook;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\GatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Receives gateway callbacks. Every delivery is authenticated before it is stored,
 * deduplicated on the gateway's event id, and handed to a queued job so the
 * gateway gets its 200 immediately.
 *
 * The endpoint is the same code for platform subscriptions and seller orders.
 * Which account a delivery belongs to is read from its transaction reference, so
 * a seller's delivery is verified with that seller's secret and a subscription's
 * with ours.
 */
class PaymentWebhookController
{
    public function paystack(Request $request, GatewayFactory $gateways): Response
    {
        $gateway = $this->gatewayFor($gateways, $request->json('data.reference'));

        $this->assertValidSignature($request, $gateway->sign($request->getContent()), (string) $request->header('x-paystack-signature'));
        $this->recordSellerWebhook($request->json('data.reference'), 'paystack');

        $this->storeAndDispatch('paystack', $request->json()->all(), $request->json('data.id'));

        return $this->acknowledge();
    }

    public function flutterwave(Request $request, GatewayFactory $gateways): Response
    {
        $gateway = $this->gatewayFor($gateways, $request->json('data.tx_ref') ?? $request->json('data.reference'));

        $this->assertValidSignature($request, $gateway->sign($request->getContent()), (string) $request->header('verif-hash'));
        $this->recordSellerWebhook($request->json('data.tx_ref') ?? $request->json('data.reference'), 'flutterwave');

        $this->storeAndDispatch('flutterwave', $request->json()->all(), $request->json('data.id') ?? $request->json('event_id'));

        return $this->acknowledge();
    }

    private function gatewayFor(GatewayFactory $gateways, mixed $reference): PaymentGatewayInterface
    {
        try {
            return $gateways->forReference(is_string($reference) ? $reference : null);
        } catch (GatewayNotConfigured) {
            // The order was charged but the seller has since removed the key. The
            // delivery can never be verified, so refuse it rather than accept an
            // unauthenticated event.
            abort(400, 'The payment account for this reference is no longer configured.');
        }
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

    private function recordSellerWebhook(mixed $reference, string $gateway): void
    {
        if (! is_string($reference) || $reference === '') {
            return;
        }

        $transaction = Transaction::withoutGlobalScopes()
            ->where('reference', $reference)
            ->first();

        if ($transaction === null || $transaction->seller_id === null) {
            return;
        }

        SellerGateway::withoutGlobalScopes()
            ->where('seller_id', $transaction->seller_id)
            ->where('gateway', $gateway)
            ->update(['last_webhook_at' => now()]);
    }

    /**
     * A replayed delivery is a no-op rather than an error.
     */
    private function storeAndDispatch(string $gateway, array $payload, mixed $eventId): void
    {
        /*
         * Every gateway delivery should carry an id. When it does not, fall
         * back to a content hash so a redelivery of the same payload is still
         * a no-op — a random UUID would silently disable the dedupe and let
         * the handler process the same event twice.
         */
        $eventId = ((is_string($eventId) && $eventId !== '') || is_int($eventId))
            ? (string) $eventId
            : 'payload:'.hash('sha256', $gateway.'|'.(json_encode($payload) ?: ''));

        $event = WebhookEvent::firstOrCreate(
            ['gateway' => $gateway, 'event_id' => $eventId],
            ['payload' => $payload],
        );

        if ($event->wasRecentlyCreated) {
            try {
                ProcessPaymentWebhook::dispatch($event->getKey());
            } catch (Throwable $exception) {
                Log::error('Payment webhook dispatch failed; sweep will retry it.', ['event_id' => $event->getKey(), 'gateway' => $gateway, 'exception' => $exception]);
                // A queue outage must not make the gateway retry an already-stored
                // event forever; the row stays unprocessed for the nightly sweep.
            }
        }
    }
}
