<?php

namespace App\Domains\Seller\Enums;

/**
 * How a business sits in the market, as one of four rungs.
 *
 * A seller picks this once. It is a rough signal ("what kind of shop am I?"),
 * not a price — which is why it is nullable on the row and why the discovery
 * filter treats null as "not ranked" rather than as the cheapest tier.
 *
 * The naira glyphs are the convention buyers already read: ₦ is what a Nigerian
 * price looks like, and four of them is visibly more than one.
 */
enum PriceTier: int
{
    case Budget = 1;
    case Standard = 2;
    case Premium = 3;
    case Luxury = 4;

    public static function tryFromRequest(mixed $value): ?self
    {
        // A shared link or a hand-typed query string must not 404 the page, so an
        // unparseable tier is dropped rather than rejected.
        if (! is_numeric($value)) {
            return null;
        }

        return self::tryFrom((int) $value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Budget => 'Budget',
            self::Standard => 'Standard',
            self::Premium => 'Premium',
            self::Luxury => 'Luxury',
        };
    }

    /**
     * The short form for a chip or a filter pill, where four words do not fit.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Budget => '₦',
            self::Standard => '₦₦',
            self::Premium => '₦₦₦',
            self::Luxury => '₦₦₦₦',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Budget => 'Lowest prices',
            self::Standard => 'Everyday pricing',
            self::Premium => 'Above average spend',
            self::Luxury => 'Top of the market',
        };
    }

    /**
     * The naira band this tier covers, for the filter pills.
     *
     * The tier itself is a seller's judgement about their market rather than a
     * price they enforce, so these are the bands buyers read the glyphs as — the
     * same ranges the design's discovery filters quote.
     */
    public function rangeLabel(): string
    {
        return match ($this) {
            self::Budget => '<25k',
            self::Standard => '25k–75k',
            self::Premium => '75k–200k',
            self::Luxury => '200k+',
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Budget, self::Standard, self::Premium, self::Luxury];
    }
}
