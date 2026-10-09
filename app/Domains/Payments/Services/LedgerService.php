<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Payout;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Double-entry money movement. Every transfer writes two immutable rows in one
 * transaction, so the signed sum of the whole ledger always nets to zero.
 */
class LedgerService
{
    /**
     * Move money between two wallets, writing the matching debit and credit rows.
     */
    public function transfer(
        Wallet $from,
        Wallet $to,
        int $amountKobo,
        string $reason,
        ?Transaction $transaction = null,
        ?Payout $payout = null,
    ): void {
        if ($amountKobo <= 0) {
            throw new RuntimeException('Ledger amounts must be positive.');
        }

        if ($from->is($to)) {
            throw new RuntimeException('Ledger transfers need two different wallets.');
        }

        DB::transaction(function () use ($from, $to, $amountKobo, $reason, $transaction, $payout) {
            $lockedFrom = Wallet::whereKey($from->getKey())->lockForUpdate()->firstOrFail();
            $lockedTo = Wallet::whereKey($to->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedFrom->balance_kobo < $amountKobo && ! $lockedFrom->canOverdraw()) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $fromBalance = $lockedFrom->balance_kobo - $amountKobo;
            $toBalance = $lockedTo->balance_kobo + $amountKobo;

            WalletLedger::create([
                'wallet_id' => $lockedFrom->getKey(),
                'transaction_id' => $transaction?->getKey(),
                'payout_id' => $payout?->getKey(),
                'direction' => 'debit',
                'amount_kobo' => $amountKobo,
                'balance_after_kobo' => $fromBalance,
                'reason' => $reason,
                'counterpart_wallet_id' => $lockedTo->getKey(),
                'created_at' => now(),
            ]);

            WalletLedger::create([
                'wallet_id' => $lockedTo->getKey(),
                'transaction_id' => $transaction?->getKey(),
                'payout_id' => $payout?->getKey(),
                'direction' => 'credit',
                'amount_kobo' => $amountKobo,
                'balance_after_kobo' => $toBalance,
                'reason' => $reason,
                'counterpart_wallet_id' => $lockedFrom->getKey(),
                'created_at' => now(),
            ]);

            $lockedFrom->update(['balance_kobo' => $fromBalance]);
            $lockedTo->update(['balance_kobo' => $toBalance]);
        });
    }

    /**
     * Settle a completed sale: the gateway clearing account pays out the seller's
     * share and the platform commission in a single balanced movement.
     */
    public function settleSale(Transaction $transaction): void
    {
        $sellerWallet = Wallet::forSeller($transaction->seller_id);

        $this->transfer(
            Wallet::platform(WalletKind::GatewayClearing),
            $sellerWallet,
            $transaction->sellerAmountKobo(),
            'sale',
            $transaction,
        );

        if ($transaction->commission_kobo > 0) {
            $this->transfer(
                Wallet::platform(WalletKind::GatewayClearing),
                Wallet::platform(WalletKind::Commission),
                $transaction->commission_kobo,
                'commission',
                $transaction,
            );
        }
    }

    /**
     * Park funds in the payouts pool while a transfer is in flight.
     */
    public function holdForPayout(Wallet $sellerWallet, Payout $payout): void
    {
        $this->transfer(
            $sellerWallet,
            Wallet::platform(WalletKind::Payouts),
            $payout->amount_kobo,
            'payout_hold',
            null,
            $payout,
        );
    }

    /**
     * Give the held funds back after a failed payout.
     */
    public function releasePayoutHold(Wallet $sellerWallet, Payout $payout): void
    {
        $this->transfer(
            Wallet::platform(WalletKind::Payouts),
            $sellerWallet,
            $payout->amount_kobo,
            'payout_release',
            null,
            $payout,
        );
    }
}
