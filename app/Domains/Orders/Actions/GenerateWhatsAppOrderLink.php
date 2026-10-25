<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use App\Shared\Support\StoreUrl;
use App\Shared\Support\WhatsApp;

class GenerateWhatsAppOrderLink
{
    /**
     * Persist the order first so the seller has a pending record and a reference
     * they can match against the incoming chat, then hand the buyer a prefilled
     * message carrying that same reference.
     *
     * @param  list<array{product_id: int, variant_id?: int|null, quantity: int}>  $cart
     * @param  array{name?: ?string, email?: ?string, phone?: ?string}  $buyer
     * @return array{order: Order, reference: string, total_kobo: int, total: string, url: string}
     */
    public function handle(StorePage $page, array $cart, array $buyer = []): array
    {
        $order = app(CreateOrderFromCheckout::class)->handle($page, $cart, $buyer, OrderSource::Whatsapp);

        $order->load('items');

        $lines = $order->items->map(
            fn ($item) => "- {$item->quantity} x {$item->name} (".Money::format($item->unit_kobo).')'
        )->implode("\n");

        $message = "Hello {$page->name}, I'd like to order (ref {$order->reference}):\n"
            .$lines."\n"
            ."\nTotal: ".Money::format($order->total_kobo)
            ."\n\nLink: ".StoreUrl::home($page);

        return [
            'order' => $order,
            'reference' => $order->reference,
            'total_kobo' => $order->total_kobo,
            'total' => Money::format($order->total_kobo),
            'url' => WhatsApp::link($page, $message),
        ];
    }
}
