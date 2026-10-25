<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\GatewayFactory;
use App\Shared\Support\StoreUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class InitializePayment
{
    public function __construct(private readonly GatewayFactory $gateways) {}

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
            $response = $this->gatewayFor($payable)->initialize(
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

    /**
     * Which account this charge belongs to.
     *
     * A subscription is the platform's own money and uses the platform account.
     * Everything else is an order or a booking, and the buyer pays the seller
     * directly through the seller's own key. There is no fallback between them:
     * a shop with no key cannot quietly start collecting on our account.
     */
    private function gatewayFor(Model $payable): PaymentGatewayInterface
    {
        if ($payable instanceof Subscription) {
            return $this->gateways->platform();
        }

        return $this->gateways->forSeller((int) $payable->seller_id);
    }

    private function defaultCallbackUrl(Model $payable, Transaction $transaction): string
    {
        $page = $payable->storePage;

        $parameters = [
            'slug' => $page?->slug,
            'reference' => $transaction->reference,
        ];

        // No store page means a subscription: the callback belongs to the platform.
        return $page
            ? StoreUrl::onCurrentHost($page, 'checkout.callback', $parameters)
            : route('checkout.callback', $parameters);
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
