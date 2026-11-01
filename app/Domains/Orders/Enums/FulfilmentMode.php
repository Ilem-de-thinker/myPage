<?php

namespace App\Domains\Orders\Enums;

use App\Domains\Orders\Actions\QuoteDelivery;

/**
 * How a placed order is fulfilled.
 *
 * Resolved by {@see QuoteDelivery} at checkout and
 * copied onto the order, so an order keeps the terms it was placed under even
 * after the seller edits their rules.
 */
enum FulfilmentMode: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup => 'Pickup',
            self::None => 'No fulfilment',
        };
    }
}
