<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Events\PaymentConfirmed;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\CommissionCalculator;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\LedgerService;
use Illuminate\Support\Facades\DB;

class ConfirmTransaction
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly CommissionCalculator $commission,
        private readonly LedgerService $ledger,
    ) {}

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

        $verification = $this->gateway->verify($reference);

        if ($verification['status'] !== 'success' || $verification['amount_kobo'] !== $existing->amount_kobo) {
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

            $transaction->update([
                'status' => TransactionStatus::Paid,
                'commission_kobo' => $this->commission->for($transaction->amount_kobo),
                'paid_at' => now(),
                'gateway_payload' => $verification['raw'],
            ]);

            $this->ledger->settleSale($transaction);

            event(new PaymentConfirmed($transaction));

            return $transaction;
        });
    }
}
