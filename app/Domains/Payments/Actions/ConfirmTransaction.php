<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Events\PaymentConfirmed;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\GatewayFactory;
use Illuminate\Support\Facades\DB;

class ConfirmTransaction
{
    public function __construct(private readonly GatewayFactory $gateways) {}

    /**
     * Re-verify with the gateway and settle. Safe to call repeatedly: a transaction
     * that is already paid short-circuits, so webhook deliveries and the browser
     * callback racing each other cannot double-credit a seller.
     */
    public function handle(string $reference): ?Transaction
    {
        $existing = Transaction::where('reference', $reference)->firstOrFail();

        if ($existing->status === TransactionStatus::Paid) {
            return $existing;
        }

        $verification = $this->gateways->forTransaction($existing)->verify($reference);

        if ($verification['status'] !== 'success' || $verification['amount_kobo'] !== $existing->amount_kobo || ($verification['currency'] ?? null) !== 'NGN') {
            $existing->update([
                'status' => TransactionStatus::Failed,
                'gateway_payload' => $verification['raw'],
            ]);

            return null;
        }

        return DB::transaction(function () use ($existing, $verification) {
            $transaction = Transaction::whereKey($existing->getKey())->lockForUpdate()->firstOrFail();

            if ($transaction->status === TransactionStatus::Paid) {
                return $transaction;
            }

            /*
             * No commission and no ledger entry. The money was paid into the
             * seller's own gateway account, so there is nothing for us to hold,
             * split or pay out; recording a fee here would be inventing a debt
             * that never existed.
             */
            $transaction->update([
                'status' => TransactionStatus::Paid,
                'paid_at' => now(),
                'gateway_payload' => $verification['raw'],
            ]);

            event(new PaymentConfirmed($transaction));

            return $transaction;
        });
    }
}
