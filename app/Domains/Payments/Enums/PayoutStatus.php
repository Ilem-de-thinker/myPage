<?php

namespace App\Domains\Payments\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';

    case Paid = 'paid';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Processing',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }
}
