<?php

namespace App\Domains\Catalog\Enums;

use App\Domains\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * The orderings the store catalog offers.
 *
 * Each case owns its own query rather than a switch statement elsewhere that maps
 * a string to a pair of orderBy calls: adding an ordering cannot then be
 * forgotten in a second place, and the builder type stays checked.
 *
 * @phpstan-type OrderFilter \Closure(\Illuminate\Database\Eloquent\Builder<\App\Domains\Orders\Models\Order>, \Illuminate\Database\Eloquent\Builder<\App\Domains\Orders\Models\OrderItem>): mixed
 */
enum ProductSort: string
{
    case Latest = 'latest';
    case PriceLow = 'price_low';
    case PriceHigh = 'price_high';
    case Popular = 'popular';

    /**
     * An unknown or missing key falls back to newest.
     *
     * The value arrives from a query string, so a stale bookmark or a hand-edited
     * URL has to land on a real page rather than an error. Newest is also the only
     * ordering that makes sense with nothing else to go on.
     */
    public static function tryFromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Latest) : self::Latest;
    }

    public function label(): string
    {
        return match ($this) {
            self::Latest => 'Newest',
            self::PriceLow => 'Price: Low to High',
            self::PriceHigh => 'Price: High to Low',
            self::Popular => 'Best Sellers',
        };
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::Latest => $query->latest('id'),
            self::PriceLow => $query->orderBy('price_kobo')->orderBy('id'),
            self::PriceHigh => $query->orderByDesc('price_kobo')->orderBy('id'),

            /*
             * Units sold, then raw traffic.
             *
             * withSum reads one correlated subquery, not one count per row, so a
             * catalogue of sixty products does not issue sixty extra queries
             * because somebody chose "Best Sellers". Only orders that actually
             * moved count: a cancelled or refunded line is not a sale, and
             * sorting it as one would put a disputed product at the top.
             *
             * The constraint is the array key, not a third argument: withSum()
             * takes exactly two. `orderItems` carries no seller scope of its own
             * and the join is on product_id, so a seller's sort cannot see
             * another seller's lines.
             */
            self::Popular => $query
                ->withSum([
                    'orderItems as sold_count' => fn (Builder $sold) => $sold->whereHas(
                        'order',
                        fn (Builder $order) => $order->withoutGlobalScopes()
                            ->whereIn('status', self::paidStatuses()),
                    ),
                ], 'quantity')
                ->orderByDesc('sold_count')
                ->orderByDesc('view_count')
                ->orderBy('id'),
        };
    }

    /**
     * The statuses a sale counts as.
     *
     * Borrowed from the billing rule that decides what a customer is worth, so
     * "best seller" and "revenue" cannot disagree about what a sale is.
     *
     * @return list<string>
     */
    public static function paidStatuses(): array
    {
        return array_values(array_filter(
            OrderStatus::cases(),
            fn (OrderStatus $status): bool => $status->countsTowardsLifetimeValue(),
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
