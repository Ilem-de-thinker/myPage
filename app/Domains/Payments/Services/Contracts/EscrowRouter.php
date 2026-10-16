<?php

namespace App\Domains\Payments\Services\Contracts;

use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Transaction;

/**
 * Decides where a completed sale's money lands before it is paid out.
 *
 * Payments knows nothing about escrow; the Billing domain binds the real answer.
 * Without this the routing decision would have to happen after settlement, by
 * which time the seller has already been paid and moving the money back would
 * credit them twice.
 */
interface EscrowRouter
{
    /**
     * @return WalletKind|null the pool to pay into instead of the seller, or
     *                         null to pay the seller normally.
     */
    public function destinationFor(Transaction $transaction): ?WalletKind;
}
