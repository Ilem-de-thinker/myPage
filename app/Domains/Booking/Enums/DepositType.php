<?php

namespace App\Domains\Booking\Enums;

/**
 * How much of the price a buyer commits to when they reserve a slot.
 */
enum DepositType: string
{
    case None = 'none';

    /** deposit_value is a kobo amount. */
    case Fixed = 'fixed';

    /** deposit_value is a whole percentage of the price. */
    case Percent = 'percent';

    /**
     * What the buyer actually pays now: the deposit, or the whole price when
     * there is no deposit to leave.
     */
    public function amountDueKobo(int $priceKobo, int $depositValue): int
    {
        $deposit = match ($this) {
            self::None => 0,
            self::Fixed => $depositValue,
            self::Percent => intdiv($priceKobo * $depositValue, 100),
        };

        // A deposit bigger than the price is a configuration mistake, not an
        // invoice, so the price is the ceiling.
        return min($deposit, $priceKobo);
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'Pay in full',
            self::Fixed => 'Fixed deposit',
            self::Percent => 'Percentage deposit',
        };
    }
}
