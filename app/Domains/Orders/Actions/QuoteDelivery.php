<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Catalog\Enums\DeliveryMode;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Enums\FulfilmentMode;
use App\Domains\Seller\Models\DeliveryProfile;
use App\Domains\Seller\Models\DeliveryZone;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Collection;

/**
 * Works out how an order is fulfilled and what delivery costs.
 *
 * Every fee here is derived from the database. The buyer may choose a zone and
 * a mode, but never a price: the same rules run again at checkout, and the
 * result is copied onto the order.
 */
final class QuoteDelivery
{
    /**
     * @param  list<array{product_id?: int, line_kobo: int}>  $lines  priced lines from PriceCart
     * @return array{mode: FulfilmentMode, fee_kobo: int, zone: string|null, zone_state: string|null, free: bool}
     */
    public function handle(
        StorePage $page,
        array $lines,
        ?int $zoneId = null,
        FulfilmentMode $requested = FulfilmentMode::Delivery,
    ): array {
        $subtotal = array_sum(array_map(
            static fn (array $line): int => (int) ($line['line_kobo'] ?? 0),
            $lines,
        ));

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('id', array_filter(array_column($lines, 'product_id')))
            ->get()
            ->keyBy('id');

        if ($requested === FulfilmentMode::Pickup || $this->isPickupOnly($products)) {
            return $this->pickup();
        }

        $profile = $page->deliveryProfile()->with('zones')->first();

        if ($profile === null) {
            // Shops that never opened delivery settings keep the arrangement
            // they already have: the address is confirmed on WhatsApp and the
            // platform invents no fee for it.
            return ['mode' => FulfilmentMode::Delivery, 'fee_kobo' => 0, 'zone' => null, 'zone_state' => null, 'free' => true];
        }

        if (! $profile->offers_delivery) {
            return $profile->offers_pickup
                ? $this->pickup()
                : ['mode' => FulfilmentMode::None, 'fee_kobo' => 0, 'zone' => null, 'zone_state' => null, 'free' => true];
        }

        $zone = $this->zone($profile, $zoneId);

        // A per-product fee is a floor, never a discount: one item with an old,
        // low override must not undercut the area the buyer is actually in.
        $override = $products
            ->pluck('delivery_fee_kobo')
            ->filter(fn (mixed $fee): bool => $fee !== null)
            ->max();

        $fee = max((int) ($zone?->fee_kobo ?? 0), (int) ($override ?? 0));

        if ($profile->free_over_kobo !== null && $subtotal >= $profile->free_over_kobo) {
            $fee = 0;
        }

        return [
            'mode' => FulfilmentMode::Delivery,
            'fee_kobo' => $fee,
            'zone' => $zone?->name,
            'zone_state' => $zone?->state,
            'free' => $fee === 0,
        ];
    }

    /** @param  Collection<int, Product>  $products */
    private function isPickupOnly(Collection $products): bool
    {
        return $products->contains(
            fn (Product $product): bool => $product->delivery_mode === DeliveryMode::Pickup,
        );
    }

    private function zone(DeliveryProfile $profile, ?int $zoneId): ?DeliveryZone
    {
        if ($zoneId !== null) {
            $zone = $profile->zones->firstWhere('id', $zoneId);

            if ($zone !== null) {
                return $zone;
            }
        }

        // Never turn a buyer away over a missing or stale area. Checkout shows
        // this shop's own areas and pre-selects the first, so falling back to
        // that same area keeps the quoted fee identical to the one on screen.
        return $profile->zones->first();
    }

    /** @return array{mode: FulfilmentMode, fee_kobo: int, zone: null, zone_state: null, free: bool} */
    private function pickup(): array
    {
        return ['mode' => FulfilmentMode::Pickup, 'fee_kobo' => 0, 'zone' => null, 'zone_state' => null, 'free' => true];
    }
}
