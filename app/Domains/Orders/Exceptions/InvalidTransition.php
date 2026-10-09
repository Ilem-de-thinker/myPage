<?php

namespace App\Domains\Orders\Exceptions;

use App\Domains\Orders\Enums\OrderStatus;
use DomainException;

class InvalidTransition extends DomainException
{
    public function __construct(public readonly OrderStatus $from, public readonly OrderStatus $to)
    {
        parent::__construct("An order cannot move from {$from->value} to {$to->value}.");
    }
}
