<?php

use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Exceptions\InvalidTransition;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Actions\RequestPayout;
use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Models\WalletLedger;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Payments\Services\CommissionCalculator;
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

    $signature = $validSignature
        ? hash_hmac('sha512', $payload, (string) config('services.paystack.secret'))
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
        'mypage.commission_bps' => 150,
        'mypage.payout_minimum_kobo' => 1000,
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

it('credits the seller wallet and the commission account when a payment lands', function () {
    [$seller, $order, $transaction] = platformOrderPendingPayment(100000);

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $transaction->refresh();

    expect($transaction->status->value)->toBe('paid')
        ->and($transaction->commission_kobo)->toBe(1500)
        ->and($transaction->paid_at)->not->toBeNull();

    expect(Wallet::forSeller($seller->id)->balance_kobo)->toBe(98500);
    expect(Wallet::platform(WalletKind::Commission)->balance_kobo)->toBe(1500);

    // The order follows the money: awaiting payment becomes paid.
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('keeps the whole ledger balanced to zero', function () {
    [, , $first] = platformOrderPendingPayment(100000);
    app(ConfirmTransaction::class)->handle($first->reference);

    [, , $second] = platformOrderPendingPayment(199900);
    app(ConfirmTransaction::class)->handle($second->reference);

    expect(Transaction::where('status', 'paid')->count())->toBe(2);

    // Two sales x two legs each x (one debit + one credit).
    expect(WalletLedger::count())->toBe(8);

    $sum = WalletLedger::get()->sum(fn (WalletLedger $entry) => $entry->signedAmountKobo());

    expect($sum)->toBe(0);
});

it('rounds commission down so rounding never favours the platform', function () {
    expect(app(CommissionCalculator::class)->for(1999))->toBe(29)
        ->and(app(CommissionCalculator::class)->for(100000))->toBe(1500);
});

it('refuses a payment that does not match the amount charged', function () {
    // The buyer only actually paid half of what the order was for.
    [$seller, , $transaction] = platformOrderPendingPayment(100000, ['amount_kobo' => 50000]);

    expect(app(ConfirmTransaction::class)->handle($transaction->reference))->toBeNull();

    expect($transaction->fresh()->status->value)->toBe('failed')
        ->and(Wallet::forSeller($seller->id)->balance_kobo)->toBe(0)
        ->and(WalletLedger::count())->toBe(0);
});

it('refuses a payment the gateway reports as unsuccessful', function () {
    [$seller, , $transaction] = platformOrderPendingPayment(100000, ['status' => 'failed']);

    app(ConfirmTransaction::class)->handle($transaction->reference);

    expect($transaction->fresh()->status->value)->toBe('failed')
        ->and(Wallet::forSeller($seller->id)->balance_kobo)->toBe(0);
});

it('is idempotent when the same payment is confirmed twice', function () {
    [$seller, , $transaction] = platformOrderPendingPayment();

    app(ConfirmTransaction::class)->handle($transaction->reference);
    app(ConfirmTransaction::class)->handle($transaction->reference);

    // A repeat confirmation must not add a single extra ledger row.
    expect(WalletLedger::count())->toBe(4)
        ->and(Wallet::forSeller($seller->id)->balance_kobo)->toBe(98500);
});

it('refuses to mutate a settled ledger row', function () {
    [, , $transaction] = platformOrderPendingPayment();

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $entry = WalletLedger::first();

    expect(fn () => $entry->update(['amount_kobo' => 1]))
        ->toThrow(RuntimeException::class, 'immutable');

    expect(fn () => $entry->delete())
        ->toThrow(RuntimeException::class, 'immutable');
});

it('accepts a correctly signed webhook and settles the order', function () {
    [$seller, $order, $transaction] = platformOrderPendingPayment(75000);

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
    [$seller, , $transaction] = platformOrderPendingPayment();

    deliverPaystackWebhook($transaction->reference, 555)->assertOk();
    deliverPaystackWebhook($transaction->reference, 555)->assertOk();

    expect(WebhookEvent::count())->toBe(1)
        ->and(WalletLedger::count())->toBe(4)
        ->and(Wallet::forSeller($seller->id)->balance_kobo)->toBe(98500);
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

it('moves money out of the wallet on a successful payout', function () {
    [$seller, , $transaction] = platformOrderPendingPayment(200000);

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $payout = app(RequestPayout::class)->handle($seller, 100000, '007', '0123456789')->fresh();

    expect($payout->status->value)->toBe('paid')
        ->and($payout->account_name)->toBe('Test Seller');

    $wallet = Wallet::forSeller($seller->id);

    expect($wallet->balance_kobo)->toBe(97000) // 197000 - 100000
        ->and($wallet->pending_kobo)->toBe(0);
});

it('gives the money back when a payout fails', function () {
    [$seller, , $transaction] = platformOrderPendingPayment(200000, ['transfer_success' => false]);

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $payout = app(RequestPayout::class)->handle($seller, 100000, '007', '0123456789');

    expect($payout->fresh()->status->value)->toBe('failed');

    $wallet = Wallet::forSeller($seller->id);

    expect($wallet->balance_kobo)->toBe(197000)
        ->and($wallet->pending_kobo)->toBe(0);

    $sum = WalletLedger::get()->sum(fn (WalletLedger $entry) => $entry->signedAmountKobo());

    expect($sum)->toBe(0);
});

it('refuses a payout below the minimum', function () {
    config(['mypage.payout_minimum_kobo' => 500000]);

    [$seller] = sellerWithPage();

    expect(fn () => app(RequestPayout::class)->handle($seller, 1000, '007', '0123456789'))
        ->toThrow(RuntimeException::class);
});
