<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Remembers a cart the moment the buyer gives us an email on the checkout page.
 * Without this the seller has no way to know the order existed.
 */
class RecordAbandonedCart
{
    /**
     * @param  list<array{product_id: int, variant_id?: int|null, quantity: int}>  $cart
     * @param  array{name?: ?string, email?: ?string, phone?: ?string}  $buyer
     */
    public function handle(StorePage $page, array $cart, array $buyer): AbandonedCart
    {
        $priced = app(PriceCart::class)->handle($page, $cart);

        $email = $buyer['email'] ?? null;
        $phone = PhoneNumber::normalize($buyer['phone'] ?? null);

        return DB::transaction(function () use ($page, $priced, $email, $phone) {
            // One open cart per buyer per store: someone editing their details and
            // blurring the field twice must not fill the seller's list with copies.
            $existing = AbandonedCart::withoutGlobalScopes()
                ->where('store_page_id', $page->getKey())
                ->open()
                ->forContact($email, $phone)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update([
                    'email' => $email ?: $existing->email,
                    'phone' => $phone ?: $existing->phone,
                    'cart' => $this->snapshot($priced['lines']),
                    'total_kobo' => $priced['total_kobo'],
                    // A fresh cart resets the reminder clock, so the seller does not
                    // chase someone who is actively checking out.
                    'reminded_at' => null,
                ]);

                return $existing;
            }

            return AbandonedCart::withoutGlobalScopes()->create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->getKey(),
                'reference' => AbandonedCart::generateReference(),
                'email' => $email,
                'phone' => $phone,
                'cart' => $this->snapshot($priced['lines']),
                'total_kobo' => $priced['total_kobo'],
                'status' => AbandonedCartStatus::Open,
            ]);
        });
    }

    /**
     * Close out carts the buyer has gone on to convert into a real order.
     *
     * Matching is by contact rather than by a token, because the buyer leaves for
     * the payment gateway in between and there is no way to thread one through
     * that redirect without trusting a query string.
     */
    public function markRecoveredBy(Order $order): int
    {
        return AbandonedCart::withoutGlobalScopes()
            ->where('store_page_id', $order->store_page_id)
            ->open()
            ->forContact($order->customer_email, $order->customer_phone)
            ->update([
                'status' => AbandonedCartStatus::Recovered,
                'recovered_order_id' => $order->getKey(),
            ]);
    }

    /**
     * Keep the snapshot readable: names and totals are what the seller acts on,
     * and a product may be renamed or deleted later.
     *
     * @param  list<array{name: string, quantity: int, line_kobo: int}>  $lines
     * @return list<array{name: string, quantity: int, line_kobo: int}>
     */
    private function snapshot(array $lines): array
    {
        return array_map(fn (array $line) => [
            'name' => $line['name'],
            'quantity' => $line['quantity'],
            'line_kobo' => $line['line_kobo'],
        ], $lines);
    }
}
