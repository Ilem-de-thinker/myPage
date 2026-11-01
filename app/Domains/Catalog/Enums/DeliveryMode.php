<?php

namespace App\Domains\Catalog\Enums;

/**
 * How one listing reaches its buyer.
 *
 * Null on a product means "inherit the store's delivery profile"; a value here
 * is the seller overriding that default for this item only.
 */
enum DeliveryMode: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Pickup => 'Pickup',
        };
    }
}
