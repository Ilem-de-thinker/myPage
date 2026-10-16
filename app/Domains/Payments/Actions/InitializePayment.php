<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class InitializePayment
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    /**
     * Create the pending transaction and hand back the gateway checkout URL.
     *
     * Callers may pass an explicit callback URL; otherwise it is derived from the
     * payable's store page, since checkout lives on the storefront routes.
     *
     * $gatewayOptions is for gateway-specific extras — a subscription passes
     * `plan` here, which turns the charge into a subscription signup.
     *
     * @param  array<string, mixed>  $gatewayOptions
     *
     * @throws Throwable Whatever the gateway raised, after the transaction has
     *                   been closed out as failed.
     */
    public function handle(Model $payable, string $email, ?string $callbackUrl = null, array $gatewayOptions = []): string
    {
        $transaction = DB::transaction(fn () => Transaction::create([
            'seller_id' => $payable->seller_id,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'reference' => $this->generateReference(),
            'gateway' => (string) config('mypage.gateway'),
            'amount_kobo' => $this->amountFor($payable),
            'status' => TransactionStatus::Pending,
        ]));

        try {
            $response = $this->gateway->initialize(
                $transaction->reference,
                $transaction->amount_kobo,
                $email,
                $callbackUrl ?? $this->defaultCallbackUrl($payable, $transaction),
                ['payable_type' => $payable->getMorphClass(), 'payable_id' => $payable->getKey()],
                $gatewayOptions,
            );
        } catch (Throwable $exception) {
            $this->abandon($transaction);

            throw $exception;
        }

        return $response['authorization_url'];
    }

    /**
     * Close out a transaction whose gateway session never opened.
     *
     * The row is written before the gateway call because the callback can only be
     * matched back by its reference, so it cannot simply be rolled back. But a
     * gateway that never answered also never opened a checkout session, so no
     * callback carrying this reference can ever arrive: leaving the row Pending
     * would advertise a payment to the buyer and to the seller's ledger that
     * nothing is going to settle. Failed says what actually happened.
     */
    private function abandon(Transaction $transaction): void
    {
        try {
            $transaction->update(['status' => TransactionStatus::Failed]);
        } catch (Throwable $reportingFailure) {
            // Never mask the gateway error the caller is about to handle.
            report($reportingFailure);
        }
    }

    private function defaultCallbackUrl(Model $payable, Transaction $transaction): string
    {
        return route('checkout.callback', [
            'slug' => $payable->storePage?->slug,
            'reference' => $transaction->reference,
        ]);
    }

    /**
     * Deposits are charged first for bookings; orders always pay in full.
     *
     * A booking carries its own rule on the model, and it has to be asked for:
     * falling through to `total_kobo` charged a booking with a deposit-free
     * service nothing at all, because a booking has no such column.
     */
    private function amountFor(Model $payable): int
    {
        if (method_exists($payable, 'amountDueKobo')) {
            return (int) $payable->amountDueKobo();
        }

        return (int) $payable->total_kobo;
    }

    private function generateReference(): string
    {
        do {
            $reference = 'MP'.Str::upper(Str::random(12));
        } while (Transaction::where('reference', $reference)->exists());

        return $reference;
    }
}
