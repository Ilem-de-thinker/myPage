<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitializePayment
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    /**
     * Create the pending transaction and hand back the gateway checkout URL.
     *
     * Callers may pass an explicit callback URL; otherwise it is derived from the
     * payable's store page, since checkout lives on the storefront routes.
     */
    public function handle(Model $payable, string $email, ?string $callbackUrl = null): string
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

        return $this->gateway->initialize(
            $transaction->reference,
            $transaction->amount_kobo,
            $email,
            $callbackUrl ?? $this->defaultCallbackUrl($payable, $transaction),
            ['payable_type' => $payable->getMorphClass(), 'payable_id' => $payable->getKey()],
        )['authorization_url'];
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
     */
    private function amountFor(Model $payable): int
    {
        if (property_exists($payable, 'deposit_kobo') && (int) $payable->deposit_kobo > 0) {
            return (int) $payable->deposit_kobo;
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
