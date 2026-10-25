<?php

namespace App\Domains\Payments\Exceptions;

use RuntimeException;

/**
 * We could not get a verdict from the gateway.
 *
 * A timeout, a 5xx, a DNS failure: the key may be perfectly good. Refusing to
 * save is still the right answer — an unproven key is exactly what this check
 * exists to prevent — but the message has to tell the seller to try again rather
 * than to change their key.
 */
class GatewayVerificationUnavailable extends RuntimeException
{
    public static function for(string $gateway): self
    {
        return new self("We could not reach {$gateway} to check that key.");
    }
}
