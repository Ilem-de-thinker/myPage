<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Escrow;
use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\EscrowRouter;

/**
 * Escrow routing for the Billing domain.
 *
 * The money already left the gateway clearing account by the time this is asked,
 * so the answer has to be "pay into escrow" rather than "move it back".
 */
class EscrowRouterService implements EscrowRouter
{
    public function destinationFor(Transaction $transaction): ?WalletKind
    {
        $payable = $transaction->payable;

        if ($payable === null) {
            return null;
        }

        if (! Feature::enabled('escrow', $transaction->seller_id)) {
            return null;
        }

        // One escrow per payable: a replayed webhook must not open a second one.
        if (Escrow::withoutGlobalScopes()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->exists()) {
            return null;
        }

        return WalletKind::Escrow;
    }
}
