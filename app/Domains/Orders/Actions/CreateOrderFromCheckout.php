<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Customers\Actions\UpsertCustomerFromOrder;
use App\Domains\Orders\Enums\FulfilmentMode;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;

class CreateOrderFromCheckout
{
    /**
     * Build an order from a cart, pricing every line from the database.
     *
     * @param  list<array{product_id: int, variant_id?: int|null, quantity: int}>  $cart
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, note?: ?string, city?: ?string, state?: ?string}  $buyer
     * @param  array{zone_id?: int|null, fulfilment?: string}|null  $delivery  null keeps the legacy WhatsApp arrangement
     */
    public function handle(StorePage $page, array $cart, array $buyer, OrderSource $source = OrderSource::Platform, ?array $delivery = null): Order
    {
        return DB::transaction(function () use ($page, $cart, $buyer, $source, $delivery) {
            $priced = app(PriceCart::class)->handle($page, $cart);

            $quote = $delivery === null
                ? ['mode' => FulfilmentMode::Delivery, 'fee_kobo' => 0, 'zone' => null, 'zone_state' => null]
                : app(QuoteDelivery::class)->handle(
                    $page,
                    $priced['lines'],
                    $delivery['zone_id'] ?? null,
                    ($delivery['fulfilment'] ?? 'delivery') === 'pickup' ? FulfilmentMode::Pickup : FulfilmentMode::Delivery,
                );

            $order = Order::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->getKey(),
                'reference' => Order::generateReference(),
                'source' => $source,
                'status' => $source->initialStatus(),
                'total_kobo' => $priced['total_kobo'] + $quote['fee_kobo'],
                'customer_name' => $buyer['name'] ?? null,
                'customer_email' => $buyer['email'],
                'customer_phone' => $buyer['phone'] ?? null,
                'customer_note' => $buyer['note'] ?? null,
                'delivery_mode' => $quote['mode'],
                'delivery_fee_kobo' => $quote['fee_kobo'],
                'delivery_zone' => $quote['zone'],
                'delivery_address' => $buyer['note'] ?? null,
                'delivery_city' => $buyer['city'] ?? null,
                'delivery_state' => $quote['zone_state'] ?? ($buyer['state'] ?? null),
            ]);

            $order->items()->createMany($priced['lines']);

            app(UpsertCustomerFromOrder::class)->handle($order);

            // Fired inside the transaction that created it, so a listener never
            // sees an order that a rollback is about to erase.
            event(new OrderPlaced($order));

            return $order;
        });
    }
}
