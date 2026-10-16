<?php

namespace App\Domains\Booking\Enums;

use Illuminate\Database\Eloquent\Builder;

/**
 * The orderings the service directory offers.
 *
 * Featured comes first here rather than latest because a service seller's page
 * is a short list a client reads once: the service they are proudest of should
 * be the first thing on it, not the one they added most recently.
 */
enum ServiceSort: string
{
    case Featured = 'featured';
    case Latest = 'latest';
    case PriceLow = 'price_low';
    case PriceHigh = 'price_high';
    case Popular = 'popular';

    /**
     * Falls back to featured, for the same reason the store falls back to newest:
     * the key came off a URL and an unrecognised one must not error.
     */
    public static function tryFromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Featured) : self::Featured;
    }

    public function label(): string
    {
        return match ($this) {
            self::Featured => 'Featured first',
            self::Latest => 'Newest',
            self::PriceLow => 'Price: Low to High',
            self::PriceHigh => 'Price: High to Low',
            self::Popular => 'Most booked',
        };
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            // The seller's own `position` is the tiebreaker: a featured service
            // still lands where they put it.
            self::Featured => $query
                ->orderByDesc('is_featured')
                ->orderBy('position')
                ->orderBy('id'),

            self::Latest => $query->latest('id'),

            self::PriceLow => $query->orderBy('price_kobo')->orderBy('id'),
            self::PriceHigh => $query->orderByDesc('price_kobo')->orderBy('id'),

            /*
             * Bookings that actually completed, then traffic. Same reasoning as
             * the store's best sellers: a cancelled or no-show appointment is not
             * a reason to rank a service higher.
             */
            self::Popular => $query
                ->withCount([
                    'bookings as booked_count' => fn (Builder $booked) => $booked
                        ->withoutGlobalScopes()
                        ->whereIn('status', self::bookedStatuses()),
                ])
                ->orderByDesc('booked_count')
                ->orderByDesc('view_count')
                ->orderBy('id'),
        };
    }

    /**
     * The booking states that count as demand.
     *
     * Borrowed from the billing rule that decides what a customer is worth, so
     * "most booked" and "revenue" cannot disagree about what a booking is.
     *
     * @return list<string>
     */
    public static function bookedStatuses(): array
    {
        return array_values(array_map(
            fn (BookingStatus $status): string => $status->value,
            array_filter(
                BookingStatus::cases(),
                fn (BookingStatus $status): bool => $status->countsTowardsLifetimeValue(),
            ),
        ));
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
