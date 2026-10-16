<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Escrow;
use App\Domains\Billing\Models\EscrowStatus;
use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Services\LedgerService;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Escrow holds a buyer's money until they are satisfied.
 *
 * Opening one is only a routing decision: the money still arrives from the
 * gateway clearing account and the commission is still taken, it simply lands in
 * the escrow pool instead of the seller's wallet. Everything else here moves it
 * back out, and refuses to move it twice.
 */
class EscrowService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Write down that a sale's money is sitting in the escrow pool.
     *
     * The money was routed there by LedgerService::settleSale at settlement time.
     * Nothing is moved here, because moving it twice would credit the seller
     * twice.
     */
    public function record(Transaction $transaction, Model $payable, StorePage $page): ?Escrow
    {
        if ($this->alreadyOpen($payable) !== null) {
            return null;
        }

        return DB::transaction(fn () => Escrow::create([
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->getKey(),
            'transaction_id' => $transaction->getKey(),
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'amount_kobo' => $transaction->sellerAmountKobo(),
            'commission_kobo' => $transaction->commission_kobo,
            'status' => EscrowStatus::Held,
            'held_at' => now(),
            'auto_releases_at' => now()->addDays(
                (int) config('mypage.escrow_auto_release_days', 7)
            ),
        ]));
    }

    /**
     * Pull an already-settled sale into escrow because the seller switched it on
     * after the buyer paid. Separate from record() because this genuinely moves
     * money out of the seller's wallet.
     */
    public function claim(Transaction $transaction, Model $payable, StorePage $page): ?Escrow
    {
        if ($this->alreadyOpen($payable) !== null) {
            return null;
        }

        return DB::transaction(function () use ($transaction, $payable, $page) {
            $this->ledger->transfer(
                Wallet::forSeller($payable->seller_id),
                Wallet::platform(WalletKind::Escrow),
                $transaction->sellerAmountKobo(),
                'escrow_claim',
                $transaction,
            );

            return Escrow::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->getKey(),
                'transaction_id' => $transaction->getKey(),
                'payable_type' => $payable->getMorphClass(),
                'payable_id' => $payable->getKey(),
                'amount_kobo' => $transaction->sellerAmountKobo(),
                'commission_kobo' => $transaction->commission_kobo,
                'status' => EscrowStatus::Held,
                'held_at' => now(),
                'auto_releases_at' => now()->addDays(
                    (int) config('mypage.escrow_auto_release_days', 7)
                ),
            ]);
        });
    }

    /**
     * Pay the held money out. Safe to call twice: a second attempt is a no-op
     * rather than a double payment.
     */
    public function release(Escrow $escrow, string $reason = 'escrow_release'): bool
    {
        return DB::transaction(function () use ($escrow, $reason) {
            $locked = Escrow::whereKey($escrow->getKey())->lockForUpdate()->firstOrFail();

            // A disputed escrow is admin-only: the whole point of the dispute is
            // that neither side may quietly take the money.
            if (! $locked->isHeld()) {
                return false;
            }

            $this->move($locked, EscrowStatus::Released, $reason, released: true);

            return true;
        });
    }

    /**
     * Return the money to the buyer. The commission is not returned: we did the
     * work, and it is already banked.
     */
    public function refund(Escrow $escrow, string $reason = 'escrow_refund'): bool
    {
        return DB::transaction(function () use ($escrow, $reason) {
            $locked = Escrow::whereKey($escrow->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isHeld()) {
                return false;
            }

            $this->move($locked, EscrowStatus::Refunded, $reason, released: false);

            return true;
        });
    }

    /**
     * Freeze the money while a dispute is looked at. Cannot be undone by a buyer
     * or a seller; only ResolveDispute decides where it goes.
     */
    public function freeze(Escrow $escrow, Seller $by, string $why): bool
    {
        if (! $escrow->isHeld()) {
            return false;
        }

        $escrow->update([
            'status' => EscrowStatus::Frozen,
            'disputed_by_seller_id' => $by->getKey(),
            'dispute_reason' => $why,
        ]);

        return true;
    }

    /**
     * The same freeze, raised by the buyer.
     *
     * Kept separate from freeze() because the dispute is then attributable to
     * nobody in the sellers table: the buyer is not a seller, and filing the
     * claim against the seller's id would put the seller's name on a complaint
     * the seller did not make. Admin reads the reason and the state, and the
     * column stays null so "seller disputed" never quietly means "buyer
     * disputed".
     */
    public function raiseBuyerDispute(Escrow $escrow, string $why): bool
    {
        if (! $escrow->isHeld()) {
            return false;
        }

        $escrow->update([
            'status' => EscrowStatus::Frozen,
            'dispute_reason' => $why,
        ]);

        return true;
    }

    public function alreadyOpen(Model $payable): ?Escrow
    {
        return Escrow::withoutGlobalScopes()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->held()
            ->first();
    }

    public function forPayable(Model $payable): ?Escrow
    {
        return Escrow::withoutGlobalScopes()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->first();
    }

    /**
     * Admin decision: the money goes one way and the dispute is closed.
     *
     * Goes through the frozen state rather than the normal paths, which refuse
     * anything not held: settling a dispute is precisely the case where a frozen
     * escrow has to move, and it must still refuse to move twice.
     */
    public function resolve(Escrow $escrow, bool $releaseToSeller): bool
    {
        return DB::transaction(function () use ($escrow, $releaseToSeller) {
            $locked = Escrow::whereKey($escrow->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [EscrowStatus::Held, EscrowStatus::Frozen], true)) {
                return false;
            }

            if ($releaseToSeller) {
                $this->move($locked, EscrowStatus::Released, 'escrow_dispute_released', released: true);
            } else {
                $this->move($locked, EscrowStatus::Refunded, 'escrow_dispute_refunded', released: false);
            }

            return true;
        });
    }

    /**
     * The only place escrow money physically changes hands.
     */
    private function move(Escrow $escrow, EscrowStatus $to, string $reason, bool $released): void
    {
        $destination = $released
            ? Wallet::forSeller($escrow->seller_id)
            : Wallet::platform(WalletKind::GatewayClearing);

        $this->ledger->transfer(
            Wallet::platform(WalletKind::Escrow),
            $destination,
            $escrow->amount_kobo,
            $reason,
            $escrow->transaction,
        );

        $escrow->update($released
            ? ['status' => $to, 'released_at' => now()]
            : ['status' => $to, 'refunded_at' => now()]);
    }
}
