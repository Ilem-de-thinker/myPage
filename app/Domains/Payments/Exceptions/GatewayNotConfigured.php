<?php

namespace App\Domains\Payments\Exceptions;

use RuntimeException;

/**
 * A seller money flow was opened for a shop with no usable gateway key.
 *
 * Failing here is the point. Falling back to the platform's own key would take
 * the buyer's money into an account that is not the seller's, which is exactly
 * the arrangement this architecture removed.
 */
class GatewayNotConfigured extends RuntimeException
{
    public static function forSeller(int $sellerId): self
    {
        return new self("Seller {$sellerId} has not connected a payment gateway.");
    }

    public static function unknown(string $gateway): self
    {
        return new self("The gateway [{$gateway}] is not supported.");
    }
}
