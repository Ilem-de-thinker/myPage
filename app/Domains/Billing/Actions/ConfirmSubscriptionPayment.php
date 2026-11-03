<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a subscription payment.
 *
 * Deliberately NOT the order path: ConfirmTransaction confirms a sale that was
 * paid into the seller's own gateway account. A subscription is the seller
 * paying us, and its money never reaches the seller's account.
 */
class ConfirmSubscriptionPayment
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly SubscriptionManager $subscriptions,
    ) {}

    /**
     * Re-verifies with the gateway, so a browser callback cannot lie. Safe to call
     * repeatedly: an already-paid transaction short-circuits, which keeps a
     * webhook and the callback racing each other from renewing twice.
     */
    /**
     * A transaction marked Paid must never leave its subscription un-activated.
     * Sellers charged before the webhook/callback ordering was fixed are
     * recovered here on their next callback instead of staying locked out.
     */
    private function reconcile(Transaction $transaction): void
    {
        $payable = $transaction->payable;

        if ($payable instanceof Subscription && $payable->status === SubscriptionStatus::Pending) {
            $this->subscriptions->renew($payable, null, $transaction->reference);
        }
    }

    public function handle(string $reference): ?Transaction
    {
        $transaction = Transaction::where('reference', $reference)->firstOrFail();

        if ($transaction->status === TransactionStatus::Paid) {
            $this->reconcile($transaction);

            return $transaction;
        }

        $verification = $this->gateway->verify($reference);

        // An underpayment is a failure, not a shorter subscription.
        if ($verification['status'] !== 'success' || $verification['amount_kobo'] !== $transaction->amount_kobo || ($verification['currency'] ?? null) !== 'NGN') {
            $transaction->update([
                'status' => TransactionStatus::Failed,
                'gateway_payload' => $verification['raw'],
            ]);

            return null;
        }

        return DB::transaction(function () use ($transaction, $verification) {
            $locked = Transaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === TransactionStatus::Paid) {
                $this->reconcile($locked);

                return $locked;
            }

            $locked->update([
                'status' => TransactionStatus::Paid,
                'paid_at' => now(),
                'gateway_payload' => $verification['raw'],
            ]);

            $subscription = $locked->payable;

            if ($subscription instanceof Subscription) {
                $this->subscriptions->renew($subscription, null, $locked->reference);
            }

            return $locked;
        });
    }
}
