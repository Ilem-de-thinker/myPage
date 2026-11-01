<?php

namespace App\Shared\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A sale is a comparison between two stored prices plus an optional window.
 *
 * There is deliberately no `on_sale` flag and no scheduled job: a future sale
 * starts, and an old one stops, the moment the clock passes the boundary. A
 * stale flag is the usual way a shop ends up advertising a discount it no
 * longer honours.
 */
trait HasSalePrice
{
    public function isOnSale(): bool
    {
        if ($this->compare_at_kobo === null || $this->compare_at_kobo <= $this->price_kobo) {
            return false;
        }

        if ($this->sale_starts_at !== null && $this->sale_starts_at->isFuture()) {
            return false;
        }

        if ($this->sale_ends_at !== null && $this->sale_ends_at->lessThanOrEqualTo(now())) {
            return false;
        }

        return true;
    }

    /**
     * The crossed-out price, only while it is actually the higher one.
     */
    public function originalPriceKobo(): ?int
    {
        return $this->isOnSale() ? $this->compare_at_kobo : null;
    }

    /**
     * What the buyer pays. `price_kobo` is already the sale price, so this is
     * the one number cart, checkout and bookings must keep using.
     */
    public function effectivePriceKobo(): int
    {
        return $this->price_kobo;
    }

    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || $this->compare_at_kobo === 0) {
            return null;
        }

        return (int) round((($this->compare_at_kobo - $this->price_kobo) / $this->compare_at_kobo) * 100);
    }

    public function saleEndsAt(): ?Carbon
    {
        return $this->isOnSale() ? $this->sale_ends_at : null;
    }

    public function saleLabel(): ?string
    {
        return $this->isOnSale() ? 'Sale' : null;
    }

    /**
     * A short line for cards: near dates get urgency, far ones get a date.
     */
    public function saleEndsLabel(): ?string
    {
        $ends = $this->saleEndsAt();

        if ($ends === null) {
            return null;
        }

        if ($ends->isToday()) {
            return 'Sale ends today';
        }

        $days = (int) now()->startOfDay()->diffInDays($ends->copy()->startOfDay());

        return $days <= 7
            ? 'Sale ends in '.$days.' '.Str::plural('day', $days)
            : 'Sale ends '.$ends->format('j M');
    }

    /**
     * The date columns every model using this trait casts.
     *
     * @return array<string, string>
     */
    protected function salePriceCasts(): array
    {
        return [
            'compare_at_kobo' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }
}
