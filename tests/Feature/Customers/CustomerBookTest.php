<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Customers\Models\Customer;
use App\Domains\Orders\Actions\CreateOrderFromCheckout;
use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * Place an order through the real checkout action so the customer book is built
 * the same way a buyer's order builds it.
 */
function orderFor(
    StorePage $page,
    Product $product,
    array $buyer = [],
    string $source = 'platform',
    int $quantity = 1,
): Order {
    return app(CreateOrderFromCheckout::class)->handle(
        $page,
        [['product_id' => $product->id, 'quantity' => $quantity]],
        // An explicit null wins, so a test can model the buyer who gave neither
        // a phone nor an email.
        ['email' => 'buyer@example.com', ...$buyer],
        OrderSource::from($source),
    );
}

it('builds a customer record the first time a buyer orders', function () {
    [$seller, $page, $product] = sellerWithProduct();

    $order = orderFor($page, $product, [
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ]);

    $customer = Customer::sole();

    expect($customer->seller_id)->toBe($seller->id)
        ->and($customer->name)->toBe('Ada Buyer')
        // Nigerian numbers are stored normalised so the same buyer matches.
        ->and($customer->phone)->toBe('+2348031234567')
        ->and($customer->order_count)->toBe(1)
        ->and($customer->lifetime_value_kobo)->toBe(0)
        ->and($order->fresh()->customer_id)->toBe($customer->id);
});

it('reuses the same customer on the buyer\'s next order', function () {
    [, $page, $product] = sellerWithProduct();

    orderFor($page, $product, ['phone' => '08031234567', 'name' => 'Ada']);
    orderFor($page, $product, ['phone' => '+234 803 123 4567', 'name' => 'Ada B']);

    $customer = Customer::sole();

    expect($customer->order_count)->toBe(2)
        ->and($customer->name)->toBe('Ada B')
        ->and(Customer::withoutGlobalScopes()->count())->toBe(1);

    expect(Order::where('customer_id', $customer->id)->count())->toBe(2);
});

it('keeps an earlier email record when the buyer later gives a phone', function () {
    [, $page, $product] = sellerWithProduct();

    // The buyer ordered with only an email, then came back with a phone.
    orderFor($page, $product, ['name' => 'Ada', 'phone' => null]);
    orderFor($page, $product, ['name' => 'Ada', 'phone' => '08031234567']);

    // Same person, so one record that now carries the stronger identifier.
    $customer = Customer::sole();

    expect($customer->order_count)->toBe(2)
        ->and($customer->phone)->toBe('+2348031234567')
        ->and($customer->identity_key)->toBe('p:+2348031234567');
});

it('never puts two sellers\' buyers in the same record', function () {
    [, $firstPage, $firstProduct] = sellerWithProduct();
    [, $secondPage, $secondProduct] = sellerWithProduct();

    orderFor($firstPage, $firstProduct, ['phone' => '08031234567']);
    orderFor($secondPage, $secondProduct, ['phone' => '08031234567']);

    // Same phone, different sellers: two businesses, two separate books.
    expect(Customer::withoutGlobalScopes()->count())->toBe(2);

    expect(Customer::withoutGlobalScopes()->pluck('seller_id')->unique())->toHaveCount(2);
});

it('gives no record to a buyer with nothing to identify them by', function () {
    [, $page, $product] = sellerWithProduct();

    $order = orderFor($page, $product, ['phone' => null, 'email' => null]);

    expect(Customer::withoutGlobalScopes()->count())->toBe(0)
        ->and($order->fresh()->customer_id)->toBeNull();
});

it('never treats an order as consent to be messaged', function () {
    [, $page, $product] = sellerWithProduct();

    orderFor($page, $product, ['phone' => '08031234567']);

    expect(Customer::sole()->opted_in)->toBeFalse();
});

it('records whatsapp orders in the customer book too', function () {
    [, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $product->id, 'qty' => 1]],
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertOk();

    $customer = Customer::sole();

    expect($customer->name)->toBe('Ada Buyer')
        ->and($customer->order_count)->toBe(1)
        ->and($customer->orders()->sole()->source->value)->toBe('whatsapp');
});

it('adds the order value to lifetime value only once it is paid', function () {
    [, $page, $product] = sellerWithProduct(['price_kobo' => 250000, 'track_stock' => false]);

    $order = orderFor($page, $product, ['phone' => '08031234567'], quantity: 2);

    // Placed but unpaid: an order count, no money.
    expect(Customer::sole()->lifetime_value_kobo)->toBe(0);

    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);

    expect(Customer::sole()->lifetime_value_kobo)->toBe(500000);
});

it('takes the money back out of lifetime value when a paid order is refunded', function () {
    [, $page, $product] = sellerWithProduct(['price_kobo' => 250000, 'track_stock' => false]);

    $order = orderFor($page, $product, ['phone' => '08031234567'], quantity: 2);

    $update = app(UpdateOrderStatus::class);
    $update->handle($order, OrderStatus::Paid);
    $update->handle($order->fresh(), OrderStatus::Processing);
    $update->handle($order->fresh(), OrderStatus::Shipped);
    $update->handle($order->fresh(), OrderStatus::Delivered);

    // Still counted once delivered.
    expect(Customer::sole()->lifetime_value_kobo)->toBe(500000);

    $update->handle($order->fresh(), OrderStatus::Refunded);

    expect(Customer::sole()->fresh()->lifetime_value_kobo)->toBe(0);
});

it('never drives a lifetime value negative', function () {
    [, $page, $product] = sellerWithProduct(['price_kobo' => 250000, 'track_stock' => false]);

    $order = orderFor($page, $product, ['phone' => '08031234567']);

    $update = app(UpdateOrderStatus::class);
    $update->handle($order, OrderStatus::Paid);
    $update->handle($order->fresh(), OrderStatus::Cancelled);

    expect(Customer::sole()->fresh()->lifetime_value_kobo)->toBe(0);
});

it('lists customers with their value and order count', function () {
    [$seller, $page, $product] = sellerWithProduct(['price_kobo' => 250000, 'track_stock' => false]);

    $order = orderFor($page, $product, ['name' => 'Ada Buyer', 'phone' => '08031234567']);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);

    actingAs($seller)->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Ada Buyer')
        ->assertSee('₦2,500')
        ->assertSee('1 order');
});

it('searches customers by name, phone or email', function () {
    [$seller, $page, $product] = sellerWithProduct();

    orderFor($page, $product, ['name' => 'Ada Buyer', 'phone' => '08031234567']);

    Customer::withoutGlobalScopes()->create([
        'seller_id' => $seller->id,
        'identity_key' => 'p:+2348099999999',
        'name' => 'Bode Seller',
        'phone' => '+2348099999999',
    ]);

    $index = route('customers.index');

    actingAs($seller)->get($index.'?q=Ada')->assertOk()->assertSee('Ada Buyer')->assertDontSee('Bode Seller');
    actingAs($seller)->get($index.'?q=08031234567')->assertOk()->assertSee('Ada Buyer')->assertDontSee('Bode Seller');
    actingAs($seller)->get($index.'?q=Bode')->assertOk()->assertSee('Bode Seller')->assertDontSee('Ada Buyer');
});

it('shows a customer with their orders, value and notes', function () {
    [$seller, $page, $product] = sellerWithProduct(['price_kobo' => 250000, 'track_stock' => false]);

    $order = orderFor($page, $product, ['name' => 'Ada Buyer', 'phone' => '08031234567']);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);

    $customer = Customer::sole();
    $customer->notes()->create(['seller_id' => $seller->id, 'body' => 'Always wants the black one.']);

    actingAs($seller)->get(route('customers.show', $customer))
        ->assertOk()
        ->assertSee('Ada Buyer')
        ->assertSee('₦2,500')
        ->assertSee($order->reference)
        ->assertSee('Always wants the black one.')
        // The number is stored and shown normalised so a buyer is only ever one row.
        ->assertSee('+2348031234567');
});

it('never shows another seller\'s customers', function () {
    [$seller] = sellerWithPage();

    $foreign = Customer::factory()->create(['name' => 'Secret Buyer']);

    actingAs($seller)->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('Secret Buyer');

    actingAs($seller)->get(route('customers.show', $foreign))->assertNotFound();
});

it('refuses to edit or note on another seller\'s customer', function () {
    [$seller] = sellerWithPage();
    $foreign = Customer::factory()->create();

    actingAs($seller)->post(route('customers.update', $foreign), ['name' => 'Hijacked'])->assertNotFound();
    actingAs($seller)->post(route('customers.notes.store', $foreign), ['body' => 'Hello'])->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Hijacked')
        ->and($foreign->notes()->count())->toBe(0);
});

it('saves a note against a customer and deletes it again', function () {
    [$seller, $page, $product] = sellerWithProduct();

    orderFor($page, $product, ['phone' => '08031234567']);
    $customer = Customer::sole();

    actingAs($seller)
        ->post(route('customers.notes.store', $customer), ['body' => 'Always wants the black one.'])
        ->assertRedirect();

    expect($customer->notes()->sole()->body)->toBe('Always wants the black one.');

    actingAs($seller)
        ->delete(route('customers.notes.destroy', [$customer, $customer->notes()->sole()]))
        ->assertRedirect();

    expect($customer->notes()->count())->toBe(0);
});

it('will not delete a note that belongs to a different customer', function () {
    [$seller, $page, $product] = sellerWithProduct();

    orderFor($page, $product, ['phone' => '08031234567']);
    orderFor($page, $product, ['phone' => '08039876543']);

    [$first, $second] = Customer::withoutGlobalScopes()->orderBy('id')->get()->all();

    $note = $second->notes()->create(['seller_id' => $seller->id, 'body' => 'Belongs elsewhere']);

    actingAs($seller)
        ->delete(route('customers.notes.destroy', [$first, $note]))
        ->assertNotFound();

    expect($second->notes()->count())->toBe(1);
});

it('lets the seller correct a name and manage consent', function () {
    [$seller, $page, $product] = sellerWithProduct();

    orderFor($page, $product, ['name' => 'ada', 'phone' => '08031234567']);
    $customer = Customer::sole();

    // Unticking the box must clear consent, not just be able to set it.
    actingAs($seller)->post(route('customers.update', $customer), [
        'name' => 'Ada Buyer',
        'opted_in' => '1',
    ])->assertRedirect();

    expect($customer->fresh()->name)->toBe('Ada Buyer')->and($customer->fresh()->opted_in)->toBeTrue();

    actingAs($seller)->post(route('customers.update', $customer), ['name' => 'Ada Buyer'])->assertRedirect();

    expect($customer->fresh()->opted_in)->toBeFalse();
});
