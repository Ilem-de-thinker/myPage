<?php

use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Notifications\OrderStatusChangedNotification;

use function Pest\Laravel\get;

it('shows an order to the buyer who owns the reference and contact', function () {
    [$seller, $page, $product] = sellerWithProduct([
        'price_kobo' => 250000,
        'track_stock' => false,
        'name' => 'Ankara Print',
    ], ['theme' => 'store', 'status' => 'published']);

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'customer_email' => 'buyer@example.com',
        'total_kobo' => 250000,
        'status' => OrderStatus::Paid,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'unit_kobo' => 250000,
        'quantity' => 1,
        'line_kobo' => 250000,
    ]);

    $track = route('storefront.track-order', ['slug' => $page->slug]);

    // The link in the buyer's status email has to actually find the order.
    get($track.'?reference='.$order->reference.'&contact=buyer@example.com')
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('Ankara Print')
        ->assertSee('₦2,500')
        ->assertSee('Paid');
});

it('matches a phone number in either format', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    $order = Order::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'customer_email' => null,
        'customer_phone' => '+2348031234567',
    ]);

    $track = route('storefront.track-order', ['slug' => $page->slug]);

    get($track.'?reference='.$order->reference.'&contact=08031234567')
        ->assertOk()
        ->assertSee($order->reference)
        ->assertDontSee('could not find');
});

it('refuses to show an order to the wrong contact', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    $order = Order::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'customer_email' => 'buyer@example.com',
    ]);

    $track = route('storefront.track-order', ['slug' => $page->slug]);

    // A correct reference with someone else's email must reveal nothing at all.
    get($track.'?reference='.$order->reference.'&contact=attacker@example.com')
        ->assertOk()
        ->assertSee('could not find')
        ->assertDontSee($order->customer_name ?? 'Ada Buyer');
});

it('will not show another store\'s order through its own tracking page', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    [, $otherPage] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    $order = Order::factory()->create([
        'store_page_id' => $otherPage->id,
        'customer_email' => 'buyer@example.com',
    ]);

    get(route('storefront.track-order', [
        'slug' => $page->slug,
        'reference' => $order->reference,
        'contact' => 'buyer@example.com',
    ]))
        ->assertOk()
        ->assertSee('could not find');
});

it('shows the form without searching when no reference is given', function () {
    [, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    get(route('storefront.track-order', ['slug' => $page->slug]))
        ->assertOk()
        ->assertSee('Track your order')
        ->assertDontSee('could not find');
});

it('includes the tracking reference in the buyer\'s status email', function () {
    Notification::fake();

    [$seller, $page, $product] = sellerWithProduct([
        'price_kobo' => 250000,
        'track_stock' => false,
    ], ['theme' => 'store', 'status' => 'published']);

    $order = Order::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'customer_email' => 'buyer@example.com',
    ]);

    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);

    Notification::assertSentOnDemand(
        OrderStatusChangedNotification::class,
        function ($notification) use ($order, $page) {
            // The mail is addressed to an anonymous mail route, so inspect the
            // built message rather than a notifiable.
            $mail = $notification->toMail(new stdClass);

            return str_contains($mail->actionUrl, $order->reference)
                && str_contains($mail->actionUrl, $page->slug);
        }
    );
});
