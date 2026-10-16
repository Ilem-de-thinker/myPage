<?php

use App\Domains\Billing\Actions\EscrowService;
use App\Domains\Billing\Models\Escrow;
use App\Domains\Billing\Models\EscrowStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Payout;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Shared\Support\Money;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * A seller with money in their wallet and a working bank transfer behind it.
 */
function sellerWithBalance(int $amountKobo = 2_000_000, array $gateway = []): array
{
    // A genuinely settled sale, so the balance comes out of the real ledger.
    // Crediting the column directly leaves the gateway clearing account short
    // and the payout hold fails on an insufficient balance.
    config(['services.paystack.secret' => 'sk_test', 'mypage.commission_bps' => 0]);

    // One fakePaystack per test: repeated calls append stubs and the first
    // matching pattern wins, so a later call cannot override an earlier one.
    fakePaystack($gateway);

    [$seller, , $product] = sellerWithProduct();

    $product->storePage->update(['status' => 'published']);

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $product->store_page_id,
        'total_kobo' => $amountKobo,
    ]);

    app(InitializePayment::class)->handle($order, 'buyer@example.com');

    $transaction = Transaction::where('payable_id', $order->id)->sole();
    app(ConfirmTransaction::class)->handle($transaction->reference);

    return [$seller, Wallet::forSeller($seller->id)->balance_kobo];
}

function bankedSeller(int $amountKobo = 2_000_000, array $gateway = []): array
{
    [$seller, $amount] = sellerWithBalance($amountKobo, $gateway);

    $seller->forceFill([
        'payout_bank_code' => '007',
        'payout_account_number' => '0123456789',
        'payout_account_name' => 'Test Seller',
    ])->save();

    return [$seller, $amount];
}

beforeEach(function () {
    config(['mypage.payout_minimum_kobo' => 1000, 'mypage.gateway' => 'paystack']);
});

it('lets a seller see their available balance without an admin in the loop', function () {
    [$seller, $amount] = sellerWithBalance(2_000_000);

    actingAs($seller)
        ->get(route('payouts.index'))
        ->assertOk()
        ->assertSee('Available to withdraw')
        ->assertSee(Money::format($amount));
});

it('shows a seller money that is in escrow but not yet theirs to take', function () {
    [$seller, $amount] = sellerWithBalance(1_000_000);

    $page = $seller->storePages()->first();
    $escrow = Escrow::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'amount_kobo' => 750_000,
        'status' => EscrowStatus::Held,
    ]);

    actingAs($seller)
        ->get(route('payouts.index'))
        ->assertOk()
        // The balance is the wallet, and escrow has not entered it.
        ->assertSee(Money::format(1_000_000))
        ->assertSee(Money::format(750_000));
});

it('does not let a seller withdraw money that is still held in escrow', function () {
    [$seller] = bankedSeller(1_000_000);

    $escrow = Escrow::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $seller->storePages()->first()->id,
        'amount_kobo' => 750_000,
        'status' => EscrowStatus::Held,
    ]);

    $wallet = Wallet::forSeller($seller->id);

    actingAs($seller)->post(route('payouts.store'), ['amount_kobo' => 1_500_000])
        ->assertSessionHasErrors('amount_kobo');

    expect($wallet->fresh()->balance_kobo)->toBe(1_000_000);
});

it('releases escrow into the wallet so the seller can withdraw it', function () {
    [$seller] = bankedSeller(500_000);

    $page = $seller->storePages()->first();
    $escrow = Escrow::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'amount_kobo' => 400_000,
        'status' => EscrowStatus::Frozen,
    ]);

    // The money is genuinely parked in the escrow pool, not the wallet.
    Wallet::platform(WalletKind::Escrow)->increment('balance_kobo', 400_000);

    $wallet = Wallet::forSeller($seller->id)->fresh();

    expect($wallet->balance_kobo)->toBe(500_000);

    app(EscrowService::class)->resolve($escrow, releaseToSeller: true);

    // Only now does it belong to the seller, and only now is it withdrawable.
    expect(Wallet::forSeller($seller->id)->fresh()->balance_kobo)->toBe(900_000)
        ->and(Wallet::platform(WalletKind::Escrow)->fresh()->balance_kobo)->toBe(0);
});

it('refuses a payout below the minimum', function () {
    [$seller] = bankedSeller(500_000);

    actingAs($seller)->post(route('payouts.store'), ['amount_kobo' => 500])
        ->assertSessionHasErrors('amount_kobo');

    expect(Payout::count())->toBe(0);
});

it('refuses a payout larger than the balance', function () {
    [$seller] = bankedSeller(500_000);

    actingAs($seller)->post(route('payouts.store'), ['amount_kobo' => 5_000_000]);

    expect(Payout::count())->toBe(0);
});

it('refuses a payout when no bank details are saved', function () {
    [$seller] = sellerWithBalance(2_000_000);

    actingAs($seller)
        ->post(route('payouts.store'), ['amount_kobo' => 1_000_000])
        ->assertSessionHasErrors('bank');

    expect(Payout::count())->toBe(0);
});

it('confirms the account name with the bank before saving details', function () {
    [$seller] = sellerWithBalance();

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('resolveAccount')
        ->once()
        ->andReturn(['account_name' => 'SOMEBODY ELSE ENTIRELY', 'raw' => []]);

    $this->app->instance(PaymentGatewayInterface::class, $gateway);

    actingAs($seller)->post(route('payouts.bank'), [
        'payout_bank_code' => '007',
        'payout_account_number' => '0123456789',
    ]);

    // The bank's answer wins over anything the seller typed, so a payout cannot
    // be quietly redirected by a form field.
    expect($seller->fresh()->payout_account_name)->toBe('SOMEBODY ELSE ENTIRELY');
});

it('rejects a bank code that is not a real bank', function () {
    [$seller] = sellerWithBalance();

    actingAs($seller)->post(route('payouts.bank'), [
        'payout_bank_code' => 'not-a-bank',
        'payout_account_number' => '0123456789',
    ])->assertStatus(422);

    expect($seller->fresh()->payout_bank_code)->toBeNull();
});

it('moves the money out of the wallet when a payout is requested', function () {
    [$seller, $balance] = bankedSeller(2_000_000);

    expect($balance)->toBe(2_000_000);

    // QUEUE_CONNECTION is sync in tests, so the transfer has already run by the
    // time the response comes back: money is out of the wallet and marked paid.
    actingAs($seller)->post(route('payouts.store'), ['amount_kobo' => 1_000_000]);

    $wallet = Wallet::forSeller($seller->id);
    $payout = Payout::sole();

    expect($wallet->fresh()->balance_kobo)->toBe(1_000_000)
        ->and($wallet->fresh()->pending_kobo)->toBe(0)
        ->and($payout->amount_kobo)->toBe(1_000_000)
        ->and($payout->status->value)->toBe('paid');
});

it('never shows one seller another seller\'s payout history', function () {
    [$mine] = bankedSeller();
    [$theirs, $amount] = sellerWithBalance(3_000_000);

    $theirs->forceFill([
        'payout_bank_code' => '007',
        'payout_account_number' => '0123456789',
        'payout_account_name' => 'Test Seller',
    ])->save();

    actingAs($theirs)->post(route('payouts.store'), ['amount_kobo' => 1_000_000]);

    actingAs($mine)->get(route('payouts.index'))
        ->assertOk()
        ->assertDontSee(Payout::sole()->reference);
});

it('gives the money back when the bank transfer fails', function () {
    [$seller] = bankedSeller(2_000_000, ['transfer_success' => false]);

    actingAs($seller)->post(route('payouts.store'), ['amount_kobo' => 1_000_000]);

    $wallet = Wallet::forSeller($seller->id);

    // A failed transfer must not cost the seller money: the hold is released.
    expect($wallet->fresh()->balance_kobo)->toBe(2_000_000)
        ->and($wallet->fresh()->pending_kobo)->toBe(0)
        ->and(Payout::sole()->status->value)->toBe('failed');
});

it('tells a seller the bank was unreachable instead of showing an error page', function () {
    // The bank lookup is a third-party call; it fails for reasons that are not
    // the seller's fault and must not read as a crash.
    [$seller] = bankedSeller(2_000_000, ['bank_resolve_success' => false]);

    actingAs($seller)
        ->post(route('payouts.store'), ['amount_kobo' => 1_000_000])
        ->assertRedirect()
        ->assertSessionHasErrors('amount_kobo');

    expect(Wallet::forSeller($seller->id)->fresh()->balance_kobo)->toBe(2_000_000);
});

it('tells a seller when their bank details could not be checked', function () {
    [$seller] = sellerWithBalance(2_000_000, ['bank_resolve_success' => false]);

    actingAs($seller)
        ->post(route('payouts.bank'), [
            'payout_bank_code' => '007',
            'payout_account_number' => '0123456789',
        ])
        ->assertSessionHasErrors('payout_account_number');

    expect($seller->fresh()->payout_account_number)->toBeNull();
});

it('requires an authenticated seller', function () {
    get(route('payouts.index'))->assertRedirect(route('login'));
    post(route('payouts.store'), ['amount_kobo' => 1])->assertRedirect(route('login'));
});
