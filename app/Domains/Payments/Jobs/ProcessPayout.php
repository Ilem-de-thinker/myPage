<?php

namespace App\Domains\Payments\Jobs;

use App\Domains\Payments\Enums\PayoutStatus;
use App\Domains\Payments\Models\Payout;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\LedgerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessPayout implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $payoutId) {}

    public function handle(PaymentGatewayInterface $gateway, LedgerService $ledger): void
    {
        $payout = Payout::findOrFail($this->payoutId);

        if ($payout->status !== PayoutStatus::Pending) {
            return;
        }

        $wallet = Wallet::forSeller($payout->seller_id);

        try {
            $recipientCode = $gateway->resolveRecipient(
                $payout->bank_code,
                $payout->account_number,
                $payout->account_name,
            );

            $result = $gateway->transfer(
                $recipientCode,
                $payout->amount_kobo,
                $payout->reference,
            );
        } catch (Throwable $exception) {
            $this->failPayout($wallet, $ledger, $payout, $exception->getMessage());

            return;
        }

        if (! $result['success']) {
            $this->failPayout($wallet, $ledger, $payout, 'The gateway rejected the transfer.');

            return;
        }

        DB::transaction(function () use ($payout, $wallet, $result) {
            $wallet->decrement('pending_kobo', $payout->amount_kobo);

            $payout->update([
                'status' => PayoutStatus::Paid,
                'gateway_reference' => $result['gateway_reference'],
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * Give the money back to the seller's available balance.
     */
    private function failPayout(Wallet $wallet, LedgerService $ledger, Payout $payout, string $reason): void
    {
        DB::transaction(function () use ($wallet, $ledger, $payout, $reason) {
            $locked = Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $locked->decrement('pending_kobo', $payout->amount_kobo);

            $ledger->releasePayoutHold($locked, $payout);

            $payout->update([
                'status' => PayoutStatus::Failed,
                'failure_reason' => $reason,
                'processed_at' => now(),
            ]);
        });
    }
}
