<?php

namespace App\Domains\Booking\Enums;

/**
 * Where a service is performed.
 *
 * Null on a service means "inherit the store's delivery profile". A salon that
 * works from its own chair and a makeup artist who travels are both services;
 * this is the one place that difference is named.
 */
enum ServiceMode: string
{
    case AtLocation = 'at_location';
    case Home = 'home';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::AtLocation => 'At your location',
            self::Home => 'Home service',
            self::Remote => 'Remote',
        };
    }
}
