<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Actions\CreateOrderFromCheckout;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Exceptions\InvalidCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('splits the inbox into whatsapp and on-platform tabs', function () {
    [$seller, , $product] = sellerWithProduct();

    $whatsapp = Order::factory()->whatsapp()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $product->store_page_id,
    ]);

    $platform = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $product->store_page_id,
    ]);

    $index = route('dashboard.orders.index');

    actingAs($seller)->get($index)
        ->assertOk()
        ->assertSee('WhatsApp')
        ->assertSee('On-platform')
        ->assertSee($whatsapp->reference)
        ->assertSee($platform->reference);

    actingAs($seller)->get($index.'?source=whatsapp')
        ->assertOk()
        ->assertSee($whatsapp->reference)
        ->assertDontSee($platform->reference);

    actingAs($seller)->get($index.'?source=platform')
        ->assertOk()
        ->assertSee($platform->reference)
        ->assertDontSee($whatsapp->reference);
});

it('gives each source the right opening status', function () {
    expect(OrderSource::Whatsapp->initialStatus())->toBe(OrderStatus::Pending->value)
        ->and(OrderSource::Platform->initialStatus())->toBe(OrderStatus::PendingPayment->value);
});

it('never shows another seller\'s orders', function () {
    [$seller, , $product] = sellerWithProduct();

    $foreign = Order::factory()->create();

    actingAs($seller)->get(route('dashboard.orders.index'))
        ->assertOk()
        ->assertDontSee($foreign->reference);

    actingAs($seller)->get(route('dashboard.orders.show', $foreign))->assertNotFound();
});

it('refuses to move another seller\'s order status', function () {
    [$seller] = sellerWithPage();
    $foreign = Order::factory()->platform()->create();

    actingAs($seller)
        ->post(route('dashboard.orders.status', $foreign), ['status' => OrderStatus::Cancelled->value])
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('shows the order detail with its items and allowed next steps', function () {
    [$seller, , $product] = sellerWithProduct();

    $order = Order::factory()->whatsapp()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $product->store_page_id,
        'customer_name' => 'Ada Buyer',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku,
        'unit_kobo' => $product->price_kobo,
        'quantity' => 2,
        'line_kobo' => $product->price_kobo * 2,
    ]);

    actingAs($seller)->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('Ada Buyer')
        ->assertSee($product->name);
});

it('re-prices a checkout from the database and ignores client prices', function () {
    [$seller, $page, $product] = sellerWithProduct([
        'price_kobo' => 250000,
        'track_stock' => false,
    ]);

    $variant = $product->variants()->create([
        'name' => 'Large',
        'price_kobo' => 300000,
        'stock_qty' => 5,
    ]);

    $order = app(CreateOrderFromCheckout::class)->handle($page, [
        ['product_id' => $product->id, 'quantity' => 2],
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ], ['email' => 'buyer@example.com']);

    expect($order->total_kobo)->toBe(800000) // 250000x2 + 300000
        ->and($order->source)->toBe(OrderSource::Platform)
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->items)->toHaveCount(2);

    expect($order->items->first()->unit_kobo)->toBe(250000)
        ->and($order->items->last()->unit_kobo)->toBe(300000);
});

it('refuses a checkout for more stock than exists', function () {
    [, $page, $product] = sellerWithProduct([
        'track_stock' => true,
        'stock_qty' => 2,
    ]);

    expect(fn () => app(CreateOrderFromCheckout::class)->handle($page, [
        ['product_id' => $product->id, 'quantity' => 5],
    ], ['email' => 'buyer@example.com']))
        ->toThrow(InvalidCart::class);
});

it('refuses a checkout for a product from another store', function () {
    [, $page] = sellerWithPage();

    [$otherSeller, $otherPage] = sellerWithPage();

    $foreignProduct = Product::factory()->create([
        'store_page_id' => $otherPage->id,
        'seller_id' => $otherSeller->id,
    ]);

    // The seller scope only applies once a tenant is resolved, exactly as the
    // storefront middleware does it.
    app()->instance(StorePage::class, $page);

    expect(fn () => app(CreateOrderFromCheckout::class)->handle($page, [
        ['product_id' => $foreignProduct->id, 'quantity' => 1],
    ], ['email' => 'buyer@example.com']))
        ->toThrow(InvalidCart::class);
});

it('saves a whatsapp order so the seller has a pending record', function () {
    [, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    $response = postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $product->id, 'qty' => 2]],
        'name' => 'Ada Buyer',
    ])->assertOk();

    $order = Order::where('reference', $response->json('reference'))->sole();

    expect($order->source)->toBe(OrderSource::Whatsapp)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total_kobo)->toBe(500000)
        ->and($order->customer_name)->toBe('Ada Buyer')
        ->and($order->items)->toHaveCount(1);

    // The buyer quotes the same reference in the WhatsApp message.
    expect(rawurldecode($response->json('url')))->toContain($order->reference);
});

it('does not decrement stock until the whatsapp order is confirmed', function () {
    [, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => true, 'stock_qty' => 5],
        ['theme' => 'store', 'status' => 'published'],
    );

    postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $product->id, 'qty' => 2]],
    ])->assertOk();

    expect($product->fresh()->stock_qty)->toBe(5);
});
