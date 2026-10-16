<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Exceptions\InvalidCart;
use App\Domains\Seller\Models\StorePage;

/**
 * Turns a cart of ids and quantities into priced lines, reading every price from
 * the database. Client supplied prices are never trusted.
 *
 * Orders and abandoned carts both go through here so the figure a seller is
 * shown for a lost cart can never drift from the figure the buyer was quoted.
 */
class PriceCart
{
    /**
     * @param  list<array{product_id: int, variant_id?: int|null, quantity: int}>  $cart
     * @return array{lines: list<array{product_id: int, variant_id: int|null, name: string, sku: string|null, unit_kobo: int, quantity: int, line_kobo: int}>, total_kobo: int}
     */
    public function handle(StorePage $page, array $cart): array
    {
        if ($cart === []) {
            throw new InvalidCart('The cart is empty.');
        }

        // The lock only bites inside a transaction, which is where checkout runs;
        // elsewhere it costs nothing and keeps the code identical.
        $products = Product::whereIn('id', array_column($cart, 'product_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $totalKobo = 0;
        $lines = [];

        foreach ($cart as $line) {
            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            /** @var Product|null $product */
            $product = $products[$line['product_id']] ?? null;

            // The product has to belong to this storefront, not just exist.
            throw_if(
                $product === null || $product->store_page_id !== $page->getKey(),
                new InvalidCart('That item is no longer available.')
            );

            throw_if($product->status !== 'active', new InvalidCart("{$product->name} is no longer on sale."));

            $variant = null;

            if (! empty($line['variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->getKey())
                    ->find($line['variant_id']) ?? throw new InvalidCart('That option is no longer available.');

                throw_if(
                    $variant->stock_qty < $quantity,
                    new InvalidCart("Only {$variant->stock_qty} x {$variant->name} left.")
                );
            } elseif ($product->track_stock && $product->stock_qty < $quantity) {
                throw new InvalidCart("Only {$product->stock_qty} x {$product->name} left.");
            }

            $unitKobo = $variant?->priceKobo() ?? $product->price_kobo;

            $totalKobo += $unitKobo * $quantity;

            $lines[] = [
                'product_id' => $product->getKey(),
                'variant_id' => $variant?->getKey(),
                'name' => $variant ? "{$product->name} — {$variant->name}" : $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'unit_kobo' => $unitKobo,
                'quantity' => $quantity,
                'line_kobo' => $unitKobo * $quantity,
            ];
        }

        return ['lines' => $lines, 'total_kobo' => $totalKobo];
    }
}
