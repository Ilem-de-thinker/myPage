<?php

namespace App\Domains\Billing\Models;

enum EscrowStatus: string
{
    /** Money is parked and the buyer has not confirmed yet. */
    case Held = 'held';

    /** Paid out to the seller. */
    case Released = 'released';

    /** Returned to the buyer. */
    case Refunded = 'refunded';

    /**
     * A dispute has frozen the money. Only an admin resolves it, so nothing
     * else may move an escrow in this state.
     */
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Held',
            self::Released => 'Released',
            self::Refunded => 'Refunded',
            self::Frozen => 'In dispute',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Held => 'bg-amber-100 text-amber-800',
            self::Released => 'bg-emerald-100 text-emerald-800',
            self::Refunded => 'bg-slate-100 text-slate-600',
            self::Frozen => 'bg-red-100 text-red-700',
        };
    }
}
