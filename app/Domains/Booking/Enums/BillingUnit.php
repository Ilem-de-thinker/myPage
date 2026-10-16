<?php

namespace App\Domains\Booking\Enums;

/**
 * What a service's price is charged per.
 *
 * A price with no unit is a number a buyer has to email about. This is the one
 * place the unit is decided, so the card, the detail page and the discovery
 * listing can never print the same service three different ways.
 */
enum BillingUnit: string
{
    case PerProject = 'per_project';
    case PerHour = 'per_hour';
    case PerMonth = 'per_month';

    public static function tryFromRequest(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }

    /**
     * Appended to the formatted price: ₦25,000 /project.
     */
    public function suffix(): string
    {
        return match ($this) {
            self::PerProject => '/project',
            self::PerHour => '/hour',
            self::PerMonth => '/month',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PerProject => 'Per project',
            self::PerHour => 'Per hour',
            self::PerMonth => 'Per month',
        };
    }

    /**
     * What one unit is, in the seller's language — "45 minutes", not "1 hour",
     * because a service priced per hour is often not an hour long.
     */
    public function noun(int $durationMinutes): string
    {
        return match ($this) {
            self::PerProject => 'project',
            self::PerHour => $durationMinutes > 0 ? self::hours($durationMinutes) : 'hour',
            self::PerMonth => 'month',
        };
    }

    private static function hours(int $minutes): string
    {
        $hours = intdiv($minutes, 60);

        return $hours < 2 ? 'hour' : 'hours';
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
