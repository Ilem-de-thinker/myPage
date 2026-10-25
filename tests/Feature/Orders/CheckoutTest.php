<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * A published store page with one active product, ready for a checkout request.
 *
 * @return array{0: StorePage, 1: Product}
 */
function checkoutStore(): array
{
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    return [$page, $product];
}

beforeEach(function () {
    config([
        'services.paystack.secret' => 'sk_test_secret',
        'mypage.gateway' => 'paystack',
    ]);

    fakePaystack();
});

it('renders a checkout page that posts the cart to the server', function () {
    [$page, $product] = checkoutStore();

    get(storeUrl($page->slug, '/checkout'))
        ->assertOk()
        ->assertSee('Checkout')
        ->assertSee(route('checkout.store', ['slug' => $page->slug]), false)
        ->assertSee('Pay now')
        ->assertDontSee((string) $product->price_kobo, false); // no server-rendered prices
});

it('creates a pending order and sends the buyer to the gateway', function () {
    [$page, $product] = checkoutStore();

    $response = post(storeUrl($page->slug, '/checkout'), [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'phone' => '08030000000',
        'cart' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    $response->assertRedirect('https://checkout.paystack.com/test-checkout');

    $order = Order::sole();

    expect($order->source)->toBe(OrderSource::Platform)
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->total_kobo)->toBe(500000)
        ->and($order->customer_name)->toBe('Ada Buyer')
        ->and($order->customer_email)->toBe('ada@example.com')
        ->and($order->store_page_id)->toBe($page->id);

    expect(Transaction::sole()->payable)->toBeInstanceOf(Order::class);
});

it('prices a variant line from the variant, ignoring anything the client sent', function () {
    [$page, $product] = checkoutStore();

    $variant = $product->variants()->create([
        'name' => 'Large',
        'price_kobo' => 400000,
        'stock_qty' => 4,
    ]);

    post(storeUrl($page->slug, '/checkout'), [
        'email' => 'ada@example.com',
        'cart' => [[
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'unit_kobo' => 1,
        ]],
    ])->assertRedirect();

    $order = Order::sole();

    expect($order->total_kobo)->toBe(400000)
        ->and($order->items->sole()->variant_id)->toBe($variant->id)
        ->and($order->items->sole()->unit_kobo)->toBe(400000);
});

it('requires an email and at least one cart line', function () {
    [$page, $product] = checkoutStore();

    post(storeUrl($page->slug, '/checkout'), ['cart' => []])->assertSessionHasErrors('email');

    post(storeUrl($page->slug, '/checkout'), ['email' => 'ada@example.com'])
        ->assertSessionHasErrors('cart');
});

it('sends the buyer back to checkout when the cart cannot be priced', function () {
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => true, 'stock_qty' => 1],
        ['theme' => 'store', 'status' => 'published'],
    );

    post(storeUrl($page->slug, '/checkout'), [
        'email' => 'ada@example.com',
        'cart' => [['product_id' => $product->id, 'quantity' => 99]],
    ])->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

it('will not check out a product belonging to another store', function () {
    [$page] = checkoutStore();

    [$otherSeller, $otherPage] = sellerWithPage();

    $foreignProduct = Product::factory()->create([
        'store_page_id' => $otherPage->id,
        'seller_id' => $otherSeller->id,
        'price_kobo' => 100,
        'track_stock' => false,
    ]);

    post(storeUrl($page->slug, '/checkout'), [
        'email' => 'ada@example.com',
        'cart' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

it('shows the thank-you page with the order lines', function () {
    [$page, $product] = checkoutStore();

    $order = Order::factory()->platform()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'total_kobo' => 500000,
        'status' => OrderStatus::Paid,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'unit_kobo' => $product->price_kobo,
        'quantity' => 2,
        'line_kobo' => 500000,
    ]);

    get(storeUrl($page->slug, "/checkout/thanks/{$order->id}"))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('order is confirmed')
        ->assertSee($product->name);
});

it('will not show the thank-you page for another store\'s order', function () {
    [$page] = checkoutStore();

    $foreign = Order::factory()->platform()->create();

    get(storeUrl($page->slug, "/checkout/thanks/{$foreign->id}"))->assertNotFound();
});

it('settles the order when the gateway callback is verified', function () {
    [$page, $product] = checkoutStore();

    post(storeUrl($page->slug, '/checkout'), [
        'email' => 'ada@example.com',
        'cart' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertRedirect();

    $transaction = Transaction::sole();

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'data' => ['status' => 'success', 'amount' => 250000, 'paid_at' => now()->toIso8601String()],
        ]),
    ]);

    get(storeUrl($page->slug, "/checkout/callback/{$transaction->reference}"))
        ->assertRedirect(route('checkout.thanks', ['slug' => $page->slug, 'order' => $transaction->payable_id]));

    expect($transaction->fresh()->status->value)->toBe('paid')
        ->and($transaction->payable->fresh()->status)->toBe(OrderStatus::Paid);
});

it('closes the transaction out as failed when the gateway cannot be reached', function () {
    [$page, $product] = checkoutStore();

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::failedConnection(),
    ]);

    post(storeUrl($page->slug, '/checkout'), [
        'email' => 'ada@example.com',
        'cart' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('email');

    // The order survives so the buyer can retry, but a transaction left Pending
    // advertises a payment that nothing is going to settle.
    expect(Transaction::sole()->status->value)->toBe('failed')
        ->and(Order::sole()->status)->toBe(OrderStatus::PendingPayment);
});

it('tells the cart which store it belongs to', function () {
    [$page, $product] = checkoutStore();

    // Every seller on the apex domain shares one host, so a cart keyed by host
    // alone belongs to whichever shop the buyer saw first — and its product ids
    // are then rejected by the next shop's checkout.
    get(storeUrl($page->slug, '/checkout'))
        ->assertOk()
        ->assertSee('data-store="'.$page->slug.'"', escape: false);
});

it('names the store on the storefront too, since the cart is filled there', function () {
    [$page, $product] = checkoutStore();

    get(storeUrl($page->slug, ''))
        ->assertOk()
        ->assertSee('data-store="'.$page->slug.'"', escape: false);
});
