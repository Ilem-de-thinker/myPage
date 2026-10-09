<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Exceptions\InvalidCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;

class CreateOrderFromCheckout
{
    /**
     * Build an order from a cart, pricing every line from the database. Client
     * supplied prices are never trusted.
     *
     * @param  list<array{product_id: int, variant_id?: int|null, quantity: int}>  $cart
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, note?: ?string}  $buyer
     */
    public function handle(StorePage $page, array $cart, array $buyer, OrderSource $source = OrderSource::Platform): Order
    {
        if ($cart === []) {
            throw new InvalidCart('The cart is empty.');
        }

        return DB::transaction(function () use ($page, $cart, $buyer, $source) {
            $products = Product::whereIn('id', array_column($cart, 'product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalKobo = 0;
            $lines = [];

            foreach ($cart as $line) {
                $quantity = max(1, (int) ($line['quantity'] ?? 1));

                /** @var Product $product */
                $product = $products[$line['product_id']] ?? throw new InvalidCart('That item is no longer available.');

                if ($product->status !== 'active') {
                    throw new InvalidCart("{$product->name} is no longer on sale.");
                }

                $variant = null;

                if (! empty($line['variant_id'])) {
                    $variant = ProductVariant::where('product_id', $product->getKey())
                        ->find($line['variant_id']) ?? throw new InvalidCart('That option is no longer available.');

                    if ($variant->stock_qty < $quantity) {
                        throw new InvalidCart("Only {$variant->stock_qty} x {$variant->name} left.");
                    }
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

            $order = Order::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->getKey(),
                'reference' => Order::generateReference(),
                'source' => $source,
                'status' => $source->initialStatus(),
                'total_kobo' => $totalKobo,
                'customer_name' => $buyer['name'] ?? null,
                'customer_email' => $buyer['email'],
                'customer_phone' => $buyer['phone'] ?? null,
                'customer_note' => $buyer['note'] ?? null,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });
    }
}
