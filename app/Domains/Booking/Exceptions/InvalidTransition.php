<?php

namespace App\Domains\Booking\Exceptions;

use RuntimeException;

class InvalidTransition extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct("A booking cannot move from {$from} to {$to}.");
    }
}
