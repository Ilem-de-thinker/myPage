<?php

use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Exceptions\InvalidTransition;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\Http;

/**
 * A platform order with one pending transaction, and Paystack stubbed to report
 * the given result for it.
 *
 * @param  array<string, mixed>  $gateway  overrides passed to fakePaystack()
 * @return array{0: Seller, 1: Order, 2: Transaction}
 */
function platformOrderPendingPayment(int $amountKobo = 100000, array $gateway = []): array
{
    [$seller, , $product] = sellerWithProduct();

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $product->store_page_id,
        'total_kobo' => $amountKobo,
    ]);

    // amount_kobo defaults to null so the stub confirms whatever the transaction
    // was actually charged; pass it only to simulate a mismatch.
    fakePaystack(['amount_kobo' => null, ...$gateway]);

    app(InitializePayment::class)->handle($order, 'buyer@example.com');

    return [$seller, $order, Transaction::where('payable_id', $order->id)->sole()];
}

/**
 * Post a Paystack delivery with a correct signature.
 */
function deliverPaystackWebhook(string $reference, int $eventId, bool $validSignature = true): mixed
{
    $payload = json_encode([
        'event' => 'charge.success',
        'data' => ['id' => $eventId, 'reference' => $reference],
    ]);

    // A seller order is charged on the seller's own account, so the delivery is
    // signed with that seller's key — not the platform's.
    $sellerId = Transaction::where('reference', $reference)->value('seller_id');
    $secret = $sellerId
        ? SellerGateway::where('seller_id', $sellerId)->where('gateway', 'paystack')->first()?->secretKey()
        : null;

    $signature = $validSignature
        ? hash_hmac('sha512', $payload, $secret ?? (string) config('services.paystack.secret'))
        : 'not-the-right-signature';

    return test()->call(
        'POST',
        '/webhooks/paystack',
        [],
        [],
        [],
        ['HTTP_X_PAYSTACK_SIGNATURE' => $signature],
        $payload,
    );
}

beforeEach(function () {
    config([
        'services.paystack.secret' => 'sk_test_secret',
        'mypage.gateway' => 'paystack',
    ]);
});

it('records a pending transaction and returns the gateway checkout url', function () {
    [$seller, $order, $transaction] = platformOrderPendingPayment(250000);

    expect($transaction->amount_kobo)->toBe(250000)
        ->and($transaction->status->value)->toBe('pending')
        ->and($transaction->payable)->toBeInstanceOf(Order::class)
        ->and($transaction->reference)->toStartWith('MP');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['amount'] === 250000);
});

it('marks the transaction paid without inventing money movement', function () {
    [, $order, $transaction] = platformOrderPendingPayment(100000);

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $transaction->refresh();

    expect($transaction->status->value)->toBe('paid')
        ->and($transaction->paid_at)->not->toBeNull();

    // The order follows the money: awaiting payment becomes paid.
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('refuses a payment that does not match the amount charged', function () {
    // The buyer only actually paid half of what the order was for.
    [, , $transaction] = platformOrderPendingPayment(100000, ['amount_kobo' => 50000]);

    expect(app(ConfirmTransaction::class)->handle($transaction->reference))->toBeNull();

    expect($transaction->fresh()->status->value)->toBe('failed');
});

it('refuses a payment the gateway reports as unsuccessful', function () {
    [, , $transaction] = platformOrderPendingPayment(100000, ['status' => 'failed']);

    app(ConfirmTransaction::class)->handle($transaction->reference);

    expect($transaction->fresh()->status->value)->toBe('failed');
});

it('is idempotent when the same payment is confirmed twice', function () {
    [, , $transaction] = platformOrderPendingPayment();

    app(ConfirmTransaction::class)->handle($transaction->reference);
    $paidAt = $transaction->fresh()->paid_at;

    app(ConfirmTransaction::class)->handle($transaction->reference);

    // A repeat confirmation must not move the settlement time or the status.
    expect($transaction->fresh()->status->value)->toBe('paid')
        ->and($transaction->fresh()->paid_at->equalTo($paidAt))->toBeTrue();
});

it('accepts a correctly signed webhook and settles the order', function () {
    [, $order, $transaction] = platformOrderPendingPayment(75000);

    deliverPaystackWebhook($transaction->reference, 987654)->assertOk();

    expect($transaction->fresh()->status->value)->toBe('paid')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(WebhookEvent::where('event_id', '987654')->sole()->processed_at)->not->toBeNull();
});

it('rejects a webhook with a bad signature', function () {
    [, , $transaction] = platformOrderPendingPayment();

    deliverPaystackWebhook($transaction->reference, 1, validSignature: false)->assertStatus(401);

    expect($transaction->fresh()->status->value)->toBe('pending')
        ->and(WebhookEvent::count())->toBe(0);
});

it('ignores a replayed webhook instead of crediting twice', function () {
    [, , $transaction] = platformOrderPendingPayment();

    deliverPaystackWebhook($transaction->reference, 555)->assertOk();
    deliverPaystackWebhook($transaction->reference, 555)->assertOk();

    expect(WebhookEvent::count())->toBe(1)
        ->and($transaction->fresh()->status->value)->toBe('paid');
});

it('walks a paid order through the status flow and records history', function () {
    [, , $transaction] = platformOrderPendingPayment();

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $order = $transaction->payable->fresh();
    $update = app(UpdateOrderStatus::class);

    $update->handle($order, OrderStatus::Processing);
    $update->handle($order->fresh(), OrderStatus::Shipped);
    $update->handle($order->fresh(), OrderStatus::Delivered);

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);

    expect($order->history()->reorder('id')->pluck('to_status')->map->value->all())
        ->toBe(['paid', 'processing', 'shipped', 'delivered']);
});

it('blocks an order from skipping states', function () {
    $order = Order::factory()->platform()->create();

    expect(fn () => app(UpdateOrderStatus::class)->handle($order, OrderStatus::Delivered))
        ->toThrow(InvalidTransition::class);
});
