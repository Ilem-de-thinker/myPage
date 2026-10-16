<?php

namespace App\Domains\Orders\Enums;

enum AbandonedCartStatus: string
{
    /** Captured at checkout, still waiting on the buyer. */
    case Open = 'open';

    /** The buyer went on to place the order, or the seller closed it out. */
    case Recovered = 'recovered';

    /** Sat untouched for so long it is no longer worth the seller's attention. */
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
