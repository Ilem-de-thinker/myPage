<?php

namespace App\Domains\Payments\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';

    case Paid = 'paid';

    case Failed = 'failed';

    case Refunded = 'refunded';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
