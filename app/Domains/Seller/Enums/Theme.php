<?php

namespace App\Domains\Seller\Enums;

enum Theme: string
{
    case Store = 'store';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Store',
            self::Service => 'Service',
        };
    }

    /**
     * The seller-facing name for the page itself, in running prose.
     *
     * Both themes really do sell something, so “storefront” stays the store
     * seller’s word; a service seller sees a website, not a shop.
     */
    public function siteNoun(): string
    {
        return match ($this) {
            self::Store => 'storefront',
            self::Service => 'website',
        };
    }

    /**
     * What this theme sells, capitalised for labels and headings.
     */
    public function offeringNoun(bool $plural = false): string
    {
        return match ($this) {
            self::Store => $plural ? 'Products' : 'Product',
            self::Service => $plural ? 'Services' : 'Service',
        };
    }

    /**
     * What buyers send in, capitalised for labels and headings.
     */
    public function transactionNoun(bool $plural = false): string
    {
        return match ($this) {
            self::Store => $plural ? 'Orders' : 'Order',
            self::Service => $plural ? 'Bookings' : 'Booking',
        };
    }

    /**
     * Every block key this theme can show, across all of its pages.
     *
     * @return list<string>
     */
    public function blockKeys(): array
    {
        $pages = config("themes.{$this->value}.pages", []);

        return collect($pages)->flatMap(fn ($p) => $p['blocks'])->unique()->values()->all();
    }

    public function pages(): array
    {
        return config("themes.{$this->value}.pages", []);
    }

    /**
     * @return list<string>
     */
    public function pageBlocks(string $page): array
    {
        return config("themes.{$this->value}.pages.{$page}.blocks", []);
    }
}
