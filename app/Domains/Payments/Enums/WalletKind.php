<?php

namespace App\Domains\Payments\Enums;

enum WalletKind: string
{
    /** Funds a seller has available to withdraw. */
    case Seller = 'seller';

    /** Gateway clearing account: debited when a buyer payment lands. */
    case GatewayClearing = 'gateway_clearing';

    /** Accrues the platform commission taken from each sale. */
    case Commission = 'commission';

    /** Holds funds earmarked for a payout while it is in flight. */
    case Payouts = 'payouts';

    /** Holds buyer money until they are satisfied, when escrow is switched on. */
    case Escrow = 'escrow';

    public function label(): string
    {
        return match ($this) {
            self::Seller => 'Seller wallet',
            self::GatewayClearing => 'Gateway clearing',
            self::Commission => 'Platform commission',
            self::Payouts => 'Payouts pool',
            self::Escrow => 'Escrow pool',
        };
    }
}
