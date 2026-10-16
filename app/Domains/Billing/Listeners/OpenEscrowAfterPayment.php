<?php

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Actions\EscrowService;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Events\PaymentConfirmed;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\WalletLedger;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Records the escrow after settlement has routed the money into the escrow pool.
 *
 * Nothing is moved here: by the time this runs the money has already landed in
 * the right place, and this only writes down that it did.
 */
class OpenEscrowAfterPayment implements ShouldQueue
{
    public function __construct(private readonly EscrowService $escrow) {}

    public function handle(PaymentConfirmed $event): void
    {
        $payable = $event->transaction->payable;

        if (! $payable instanceof Order || ! $payable->storePage) {
            return;
        }

        // Only recorded when the settlement actually routed into escrow.
        if ($this->escrow->alreadyOpen($payable) !== null) {
            return;
        }

        $page = $payable->storePage;

        /*
         * Ask the ledger, not the feature flag.
         *
         * The money was routed at settlement time by EscrowRouter. This listener
         * runs later, off a queue, and by then the flag may have been turned off
         * — or on, for a sale that settled before anyone enabled it. Either way
         * the flag answers a question about now, while the question here is where
         * the money went then.
         *
         * Getting this wrong records an escrow for a sale that was paid straight
         * to the seller: the pool holds nothing, the row claims it does, and the
         * first release pays the seller a second time.
         */
        if (! $this->settledIntoEscrow($event->transaction)) {
            return;
        }

        $this->escrow->record($event->transaction, $payable, $page);
    }

    /**
     * Did this transaction's seller share actually land in the escrow pool?
     *
     * LedgerService names the movement 'sale_to_escrow' when it routed there, so
     * that row is the proof. Checking it directly means this stays correct no
     * matter what the flags say now.
     */
    private function settledIntoEscrow(Transaction $transaction): bool
    {
        return WalletLedger::query()
            ->where('transaction_id', $transaction->getKey())
            ->where('reason', 'sale_to_escrow')
            ->exists();
    }
}
