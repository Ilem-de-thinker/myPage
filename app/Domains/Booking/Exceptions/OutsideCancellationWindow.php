<?php

namespace App\Domains\Booking\Exceptions;

use RuntimeException;

class OutsideCancellationWindow extends RuntimeException
{
    public function __construct(public readonly int $hoursLeft)
    {
        parent::__construct(
            "This booking starts in {$hoursLeft} hours, inside your cancellation window. "
            .'Contact the customer directly to change it.'
        );
    }
}
