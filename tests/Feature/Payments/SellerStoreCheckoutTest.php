<?php

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Exceptions\GatewayNotConfigured;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\post;

/**
 * A published store with a product, and the seller's gateway key set to a value
 * no other configuration uses.
 *
 * @return array{0: Seller, 1: StorePage, 2: array<string, mixed>, 3: SellerGateway}
 */
function storeWithSellerKey(string $secret = 'sk_test_seller_account_key'): array
{
    [$seller, $page, $product] = sellerWithProduct(
        ['price_kobo' => 250000, 'track_stock' => false],
        ['theme' => 'store', 'status' => 'published'],
    );

    $gateway = SellerGateway::where('seller_id', $seller->id)->sole();
    $gateway->setSecretKey($secret);
    $gateway->save();

    $cart = [
        'email' => 'ada@example.com',
        'cart' => [['product_id' => $product->id, 'quantity' => 1]],
    ];

    return [$seller, $page, $cart, $gateway];
}

beforeEach(function () {
    config([
        'services.paystack.secret' => 'sk_test_platform_account_key',
        'mypage.gateway' => 'paystack',
    ]);

    fakePaystack();
});

it('initialises checkout with the seller key, never the platform key', function () {
    [$seller, $page, $cart, $gateway] = storeWithSellerKey();

    post(storeUrl($page->slug, '/checkout'), $cart)
        ->assertRedirect('https://checkout.paystack.com/test-checkout');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/initialize')
        && $request->hasHeader('Authorization', 'Bearer '.$gateway->secretKey()));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/transaction/initialize')
        && $request->hasHeader('Authorization', 'Bearer sk_test_platform_account_key'));

    expect(Order::where('seller_id', $seller->id)->sole()->status)->toBe(OrderStatus::PendingPayment);
});

it('keeps a custom-domain buyer on their own domain when the gateway returns', function () {
    [$seller, $page, $cart] = storeWithSellerKey();

    DomainBinding::withoutGlobalScopes()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'host' => 'shop.example',
        'status' => DomainStatus::Active,
    ]);

    post('http://shop.example/checkout', $cart)
        ->assertRedirect('https://checkout.paystack.com/test-checkout');

    $reference = Transaction::sole()->reference;

    Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/initialize')
        && $request['callback_url'] === "http://shop.example/checkout/callback/{$reference}");

    expect($reference)->not->toBeEmpty();
});

it('re-verifies against the seller account that took the money', function () {
    [, $page, $cart, $gateway] = storeWithSellerKey();

    post(storeUrl($page->slug, '/checkout'), $cart);

    $reference = Transaction::sole()->reference;

    app(ConfirmTransaction::class)->handle($reference);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/verify/'.$reference)
        && $request->hasHeader('Authorization', 'Bearer '.$gateway->secretKey()));
});

it('accepts a webhook signed with the seller secret', function () {
    [, $page, $cart, $gateway] = storeWithSellerKey();

    post(storeUrl($page->slug, '/checkout'), $cart);

    $reference = Transaction::sole()->reference;
    $payload = json_encode(['event' => 'charge.success', 'data' => ['id' => 321, 'reference' => $reference]]);

    test()->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, $gateway->secretKey()),
    ], $payload)->assertOk();

    expect(Transaction::sole()->fresh()->status->value)->toBe('paid')
        ->and(WebhookEvent::where('event_id', '321')->sole()->processed_at)->not->toBeNull();
});

it('rejects a seller order webhook signed with the platform secret', function () {
    [, $page, $cart] = storeWithSellerKey();

    post(storeUrl($page->slug, '/checkout'), $cart);

    $reference = Transaction::sole()->reference;
    $payload = json_encode(['event' => 'charge.success', 'data' => ['id' => 322, 'reference' => $reference]]);

    test()->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_platform_account_key'),
    ], $payload)->assertStatus(401);

    expect(Transaction::sole()->fresh()->status->value)->toBe('pending')
        ->and(WebhookEvent::count())->toBe(0);
});

it('saves the order but refuses to charge when the shop has no gateway', function () {
    [$seller, $page, $cart] = storeWithSellerKey();

    SellerGateway::where('seller_id', $seller->id)->delete();

    post(storeUrl($page->slug, '/checkout'), $cart)
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(Order::where('seller_id', $seller->id)->sole()->status)->toBe(OrderStatus::PendingPayment);

    Http::assertNothingSent();
});

it('will not quietly charge the platform account for a seller with no key', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    SellerGateway::where('seller_id', $seller->id)->delete();

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => 250000,
    ]);

    expect(fn () => app(InitializePayment::class)->handle($order, 'ada@example.com'))
        ->toThrow(GatewayNotConfigured::class);

    Http::assertNothingSent();
});
