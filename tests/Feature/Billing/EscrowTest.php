<?php

use App\Domains\Billing\Actions\EscrowService;
use App\Domains\Billing\Jobs\ReleaseAutoEscrow;
use App\Domains\Billing\Models\Escrow;
use App\Domains\Billing\Models\EscrowStatus;
use App\Domains\Billing\Services\Feature;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Models\WalletLedger;
use App\Domains\Seller\Models\Seller;

/**
 * A paid platform order, settled through the real ledger. Returns the seller,
 * order and transaction so a test can look at either end of the money.
 *
 * @return array{0: Seller, 1: Order, 2: Transaction}
 */
function settledOrder(int $amountKobo = 100000): array
{
    [$seller, , $product] = sellerWithProduct();

    $page = $product->storePage;
    $page->update(['status' => 'published']);

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => $amountKobo,
    ]);

    config(['services.paystack.secret' => 'sk_test', 'mypage.gateway' => 'paystack', 'mypage.commission_bps' => 150]);
    fakePaystack();

    app(InitializePayment::class)->handle($order, 'buyer@example.com');

    $transaction = Transaction::where('payable_id', $order->id)->sole();
    app(ConfirmTransaction::class)->handle($transaction->reference);

    return [$seller, $order->fresh(), $transaction->fresh()];
}

function sellerBalance(int $sellerId): int
{
    return Wallet::forSeller($sellerId)->balance_kobo;
}

function poolBalance(WalletKind $kind): int
{
    return Wallet::platform($kind)->balance_kobo;
}

beforeEach(function () {
    config(['mypage.features.escrow' => false]);
    Feature::flush();
});

it('pays the seller directly when escrow is off', function () {
    [$seller, , $transaction] = settledOrder(100000);

    expect(sellerBalance($seller->id))->toBe(98500)
        ->and(Escrow::withoutGlobalScopes()->count())->toBe(0)
        ->and(poolBalance(WalletKind::Escrow))->toBe(0)
        ->and($transaction->commission_kobo)->toBe(1500);
});

it('parks the money in escrow instead of paying the seller', function () {
    config(['mypage.features.escrow' => true, 'mypage.escrow_auto_release_days' => 7]);
    Feature::flush();

    [$seller, $order] = settledOrder(100000);

    // The seller is not paid yet.
    expect(sellerBalance($seller->id))->toBe(0)
        ->and(poolBalance(WalletKind::Escrow))->toBe(98500);

    $escrow = Escrow::withoutGlobalScopes()->sole();

    expect($escrow->payable_id)->toBe($order->id)
        ->and($escrow->amount_kobo)->toBe(98500)
        ->and($escrow->isHeld())->toBeTrue()
        ->and($escrow->auto_releases_at)->not->toBeNull();
});

it('releases escrow to the seller and cannot pay twice', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller] = settledOrder(100000);
    $escrow = Escrow::withoutGlobalScopes()->sole();

    expect(app(EscrowService::class)->release($escrow))->toBeTrue();

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Released)
        ->and($escrow->fresh()->released_at)->not->toBeNull()
        ->and(sellerBalance($seller->id))->toBe(98500);

    // A replayed release must not pay the seller a second time.
    expect(app(EscrowService::class)->release($escrow))->toBeFalse()
        ->and(sellerBalance($seller->id))->toBe(98500);
});

it('returns the money to the buyer on a refund', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller] = settledOrder(100000);
    $escrow = Escrow::withoutGlobalScopes()->sole();

    expect(app(EscrowService::class)->refund($escrow))->toBeTrue();

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Refunded)
        ->and(sellerBalance($seller->id))->toBe(0)
        ->and(poolBalance(WalletKind::Escrow))->toBe(0);
});

it('releases escrow automatically once the buyer has had long enough', function () {
    config(['mypage.features.escrow' => true, 'mypage.escrow_auto_release_days' => 7]);
    Feature::flush();

    [$seller] = settledOrder(100000);
    $escrow = Escrow::withoutGlobalScopes()->sole();

    // Too early.
    app(ReleaseAutoEscrow::class)->handle(app(EscrowService::class));

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Held)
        ->and(sellerBalance($seller->id))->toBe(0);

    $this->travel(8)->days();

    app(ReleaseAutoEscrow::class)->handle(app(EscrowService::class));

    // The buyer's silence must not hold a seller's money forever.
    expect($escrow->fresh()->status)->toBe(EscrowStatus::Released)
        ->and(sellerBalance($seller->id))->toBe(98500);
});

it('freezes escrow on a dispute and refuses every other route', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller] = settledOrder(100000);
    $escrow = Escrow::withoutGlobalScopes()->sole();

    expect(app(EscrowService::class)->freeze($escrow, $seller, 'Never arrived'))->toBeTrue();

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Frozen)
        ->and($escrow->fresh()->dispute_reason)->toBe('Never arrived');

    // Neither a release nor an auto-release may touch a disputed escrow.
    app(EscrowService::class)->release($escrow);
    $this->travel(30)->days();
    app(ReleaseAutoEscrow::class)->handle(app(EscrowService::class));

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Frozen)
        ->and(sellerBalance($seller->id))->toBe(0);
});

it('lets an admin decision settle a disputed escrow either way', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller] = settledOrder(100000);
    $escrow = Escrow::withoutGlobalScopes()->sole();

    app(EscrowService::class)->freeze($escrow, $seller, 'Wrong colour');

    expect(app(EscrowService::class)->resolve($escrow, releaseToSeller: true))->toBeTrue()
        ->and($escrow->fresh()->status)->toBe(EscrowStatus::Released)
        ->and(sellerBalance($seller->id))->toBe(98500);

    // And the other way round, on a fresh one.
    [$otherSeller] = settledOrder(200000);
    $other = Escrow::withoutGlobalScopes()->where('seller_id', $otherSeller->id)->sole();
    app(EscrowService::class)->freeze($other, $otherSeller, 'Never arrived');

    expect(app(EscrowService::class)->resolve($other, releaseToSeller: false))->toBeTrue()
        ->and($other->fresh()->status)->toBe(EscrowStatus::Refunded)
        ->and(sellerBalance($otherSeller->id))->toBe(0);
});

it('opens only one escrow for an order, however many times it is settled', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller, $order, $transaction] = settledOrder(100000);

    // A replayed webhook short-circuits in ConfirmTransaction, and the escrow
    // router refuses a second one regardless.
    app(ConfirmTransaction::class)->handle($transaction->reference);

    expect(Escrow::withoutGlobalScopes()->count())->toBe(1)
        ->and(poolBalance(WalletKind::Escrow))->toBe(98500);
});

it('keeps the ledger balanced while money sits in escrow', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller] = settledOrder(100000);
    $escrow = Escrow::withoutGlobalScopes()->sole();
    app(EscrowService::class)->release($escrow);

    // Every movement writes a debit and a credit, so the signed sum is zero.
    $net = WalletLedger::query()
        ->selectRaw('SUM(CASE WHEN direction = \'credit\' THEN amount_kobo ELSE -amount_kobo END) as net')
        ->value('net');

    expect((int) $net)->toBe(0);
});

it('keeps every seller\'s escrow on their own money', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$firstSeller] = settledOrder(100000);
    [$secondSeller] = settledOrder(300000);

    $first = Escrow::forSeller($firstSeller->id)->sole();
    $second = Escrow::forSeller($secondSeller->id)->sole();

    expect($first->amount_kobo)->toBe(98500)
        ->and($second->amount_kobo)->toBe(295500);

    // Releasing one seller's escrow pays only that seller.
    app(EscrowService::class)->release($first);

    expect(sellerBalance($firstSeller->id))->toBe(98500)
        ->and(sellerBalance($secondSeller->id))->toBe(0)
        ->and($second->fresh()->isHeld())->toBeTrue();
});

/**
 * Escrow has to be a record of where the money actually went, not of what the
 * feature flag says today. These two cases are where those two ideas come apart.
 */
it('opens no escrow for a sale that was settled straight to the seller', function () {
    // Escrow is off when the sale settles, so the money goes to the wallet.
    config(['mypage.features.escrow' => false]);
    Feature::flush();

    [$seller, $order] = settledOrder(100000);

    expect(Wallet::forSeller($seller->id)->balance_kobo)->toBeGreaterThan(0)
        ->and(Escrow::withoutGlobalScopes()->where('payable_id', $order->id)->count())->toBe(0);
});

it('does not backfill an escrow when the flag is switched on after settlement', function () {
    config(['mypage.features.escrow' => false]);
    Feature::flush();

    [$seller, $order] = settledOrder(100000);

    // The seller has been paid. Turning the flag on now cannot conjure money out
    // of the escrow pool, because none was ever put there.
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    app(ReleaseAutoEscrow::class)->handle(app(EscrowService::class));

    expect(Escrow::withoutGlobalScopes()->where('payable_id', $order->id)->count())->toBe(0)
        ->and(Wallet::forSeller($seller->id)->balance_kobo)->toBeGreaterThan(0);
});

it('holds exactly what the escrow pool holds', function () {
    config(['mypage.features.escrow' => true]);
    Feature::flush();

    [$seller, $order] = settledOrder(100000);
    [$seller, $order] = settledOrder(60000);

    expect(Escrow::withoutGlobalScopes()->count())->toBe(2);

    // The single invariant everything else rests on: the sum of held escrows can
    // never be more than the money actually parked in the pool.
    $heldInRows = (int) Escrow::withoutGlobalScopes()->held()->sum('amount_kobo');
    $inPool = Wallet::platform(WalletKind::Escrow)->balance_kobo;

    expect($heldInRows)->toBe($inPool);
});
