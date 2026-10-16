<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Actions\CloseStaleAbandonedCarts;
use App\Domains\Orders\Actions\CreateOrderFromCheckout;
use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Jobs\RemindAbandonedCarts;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Notifications\AbandonedCartReminder;
use App\Domains\Orders\Notifications\NewOrderNotification;
use App\Domains\Orders\Notifications\OrderStatusChangedNotification;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * Capture a cart the way the checkout page does when the buyer leaves the email
 * field, priced from the database.
 */
function captureCart(StorePage $page, array $items, array $buyer = []): TestResponse
{
    return postJson("/{$page->slug}/checkout/capture", [
        'items' => $items,
        'email' => 'buyer@example.com',
        ...$buyer,
    ]);
}

it('captures a cart when the buyer leaves their email at checkout', function () {
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    captureCart($page, [['id' => $product->id, 'qty' => 2]], ['phone' => '08031234567'])
        ->assertOk()
        ->assertJsonPath('total_kobo', 500000);

    $cart = AbandonedCart::sole();

    expect($cart->store_page_id)->toBe($page->id)
        ->and($cart->seller_id)->toBe($seller->id)
        ->and($cart->status)->toBe(AbandonedCartStatus::Open)
        ->and($cart->email)->toBe('buyer@example.com')
        ->and($cart->phone)->toBe('+2348031234567')
        ->and($cart->cart[0])->toMatchArray(['name' => $product->name, 'quantity' => 2, 'line_kobo' => 500000]);
});

it('keeps one open cart per buyer instead of a copy per blur', function () {
    [, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    captureCart($page, [['id' => $product->id, 'qty' => 1]])->assertOk();
    captureCart($page, [['id' => $product->id, 'qty' => 3]])->assertOk();

    expect(AbandonedCart::withoutGlobalScopes()->count())->toBe(1)
        ->and(AbandonedCart::sole()->total_kobo)->toBe(750000);
});

it('will not capture a cart with no way to reach the buyer', function () {
    [, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    captureCart($page, [['id' => $product->id, 'qty' => 1]], ['email' => null, 'phone' => null])
        ->assertStatus(422);

    expect(AbandonedCart::withoutGlobalScopes()->count())->toBe(0);
});

it('prices the captured cart from the database and refuses a foreign item', function () {
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    [, $otherPage, $otherProduct] = sellerWithProduct(
        ['price_kobo' => 999999, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    // A tampered price from the client is ignored.
    captureCart($page, [['id' => $product->id, 'qty' => 1, 'price_kobo' => 1]])
        ->assertOk()
        ->assertJsonPath('total_kobo', 250000);

    // Another store's product is not orderable through this storefront.
    captureCart($page, [['id' => $otherProduct->id, 'qty' => 1]])->assertStatus(422);

    expect(AbandonedCart::withoutGlobalScopes()->count())->toBe(1);
});

it('closes the cart once the buyer goes on to check out', function () {
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    captureCart($page, [['id' => $product->id, 'qty' => 2]], ['phone' => '08031234567'])->assertOk();

    config(['services.paystack.secret' => 'sk_test', 'mypage.gateway' => 'paystack']);
    fakePaystack();

    post("/{$page->slug}/checkout", [
        'email' => 'buyer@example.com',
        'phone' => '08031234567',
        'cart' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertRedirect();

    $cart = AbandonedCart::sole();

    expect($cart->status)->toBe(AbandonedCartStatus::Recovered)
        ->and($cart->recovered_order_id)->not->toBeNull()
        ->and($cart->recoveredOrder)->toBeInstanceOf(Order::class);
});

it('leaves another buyer\'s cart open when someone else checks out', function () {
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    captureCart($page, [['id' => $product->id, 'qty' => 1]], ['email' => 'ada@example.com'])->assertOk();
    captureCart($page, [['id' => $product->id, 'qty' => 1]], ['email' => 'bode@example.com'])->assertOk();

    config(['services.paystack.secret' => 'sk_test', 'mypage.gateway' => 'paystack']);
    fakePaystack();

    post("/{$page->slug}/checkout", [
        'email' => 'ada@example.com',
        'cart' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(AbandonedCart::withoutGlobalScopes()->where('status', 'open')->count())->toBe(1)
        ->and(AbandonedCart::withoutGlobalScopes()->where('email', 'bode@example.com')->sole()->status)
        ->toBe(AbandonedCartStatus::Open);
});

it('lists abandoned carts for the seller and never another seller\'s', function () {
    [$seller, $page, $product] = sellerWithProduct();

    AbandonedCart::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'email' => 'ada@example.com',
    ]);

    $foreign = AbandonedCart::factory()->create(['email' => 'secret@example.com']);

    actingAs($seller)->get(route('abandoned-carts.index'))
        ->assertOk()
        ->assertSee('ada@example.com')
        ->assertDontSee('secret@example.com');

    actingAs($seller)->delete(route('abandoned-carts.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh()->status)->toBe(AbandonedCartStatus::Open);
});

it('lets the seller close a cart by hand', function () {
    [$seller, $page] = sellerWithPage();

    $cart = AbandonedCart::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->delete(route('abandoned-carts.destroy', $cart))->assertRedirect();

    expect($cart->fresh()->status)->toBe(AbandonedCartStatus::Recovered);
});

it('emails a buyer once for a cart they left behind', function () {
    Notification::fake();

    $cart = AbandonedCart::factory()->create([
        'created_at' => now()->subHours(30),
        'updated_at' => now()->subHours(30),
    ]);

    app(RemindAbandonedCarts::class)->handle();

    Notification::assertSentOnDemand(
        AbandonedCartReminder::class,
        fn (AbandonedCartReminder $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === $cart->email
    );

    expect($cart->fresh()->reminded_at)->not->toBeNull();
});

it('does not email a fresh cart, a reminded one, or one with no email', function () {
    Notification::fake();

    $old = now()->subHours(30);

    // Too recent to chase.
    AbandonedCart::factory()->create(['created_at' => now(), 'updated_at' => now()]);
    // Already chased.
    AbandonedCart::factory()->reminded()->create(['created_at' => $old, 'updated_at' => $old]);
    // No way to reach them.
    AbandonedCart::factory()->create(['created_at' => $old, 'updated_at' => $old, 'email' => null]);

    app(RemindAbandonedCarts::class)->handle();

    Notification::assertNothingSent();
});

it('never emails the same cart twice', function () {
    Notification::fake();

    $old = now()->subHours(30);
    $cart = AbandonedCart::factory()->create(['created_at' => $old, 'updated_at' => $old]);

    app(RemindAbandonedCarts::class)->handle();
    app(RemindAbandonedCarts::class)->handle();

    Notification::assertSentTimes(AbandonedCartReminder::class, 1);
});

it('retires carts nobody ever came back for', function () {
    $stale = AbandonedCart::factory()->create([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $recent = AbandonedCart::factory()->create([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    app(CloseStaleAbandonedCarts::class)->handle();

    expect($stale->fresh()->status)->toBe(AbandonedCartStatus::Expired)
        ->and($recent->fresh()->status)->toBe(AbandonedCartStatus::Open);
});

it('tells the seller about a new order', function () {
    Notification::fake();

    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    captureCart($page, [['id' => $product->id, 'qty' => 1]])->assertOk();
    postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $product->id, 'qty' => 1]],
        'name' => 'Ada Buyer',
    ])->assertOk();

    Notification::assertSentTo($seller, NewOrderNotification::class);
});

it('tells a buyer about the order moves they care about and skips the rest', function () {
    Notification::fake();

    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    $order = app(CreateOrderFromCheckout::class)->handle(
        $page,
        [['product_id' => $product->id, 'quantity' => 1]],
        ['email' => 'buyer@example.com', 'name' => 'Ada'],
    );

    Notification::assertSentTimes(OrderStatusChangedNotification::class, 0);

    // Processing is the seller prepping; the buyer needs no noise about it.
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);
    app(UpdateOrderStatus::class)->handle($order->fresh(), OrderStatus::Processing);

    Notification::assertSentTimes(OrderStatusChangedNotification::class, 1);

    app(UpdateOrderStatus::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(UpdateOrderStatus::class)->handle($order->fresh(), OrderStatus::Delivered);

    Notification::assertSentTimes(OrderStatusChangedNotification::class, 3);

    Notification::assertSentOnDemand(
        OrderStatusChangedNotification::class,
        fn (OrderStatusChangedNotification $n, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'buyer@example.com'
    );
});

it('sends nothing to a buyer who gave no email', function () {
    Notification::fake();

    [, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    $order = app(CreateOrderFromCheckout::class)->handle(
        $page,
        [['product_id' => $product->id, 'quantity' => 1]],
        ['email' => null, 'phone' => '08031234567'],
    );

    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);

    // A phone number is not a substitute for an email we can send to.
    Notification::assertSentTimes(OrderStatusChangedNotification::class, 0);
});
