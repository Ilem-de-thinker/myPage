<?php

namespace App\Domains\Booking\Exceptions;

use RuntimeException;

/**
 * No property is promoted here on purpose: Exception already declares $message,
 * and redeclaring it as readonly is a compile time fatal.
 */
class SlotUnavailable extends RuntimeException
{
    public function __construct(string $message = 'That time is no longer available.')
    {
        parent::__construct($message);
    }
}
