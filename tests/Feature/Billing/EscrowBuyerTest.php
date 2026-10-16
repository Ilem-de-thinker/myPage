<?php

use App\Domains\Billing\Models\Escrow;
use App\Domains\Billing\Models\EscrowStatus;
use App\Domains\Billing\Services\Feature;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * A real paid order that routed its money into escrow, settled through the real
 * ledger. Nothing is faked except the gateway itself.
 *
 * @return array{0: Seller, 1: Order, 2: Escrow}
 */
function escrowedOrder(int $amountKobo = 100000): array
{
    [$seller, , $product] = sellerWithProduct();

    $page = $product->storePage;
    $page->update(['status' => 'published']);

    config([
        'services.paystack.secret' => 'sk_test',
        'mypage.gateway' => 'paystack',
        'mypage.commission_bps' => 150,
        'mypage.features.escrow' => true,
    ]);

    Feature::flush();
    fakePaystack();

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => $amountKobo,
    ]);

    app(InitializePayment::class)->handle($order, 'buyer@example.com');

    $transaction = Transaction::where('payable_id', $order->id)->sole();
    app(ConfirmTransaction::class)->handle($transaction->reference);

    $escrow = Escrow::withoutGlobalScopes()
        ->where('payable_type', $order->getMorphClass())
        ->where('payable_id', $order->getKey())
        ->sole();

    return [$seller, $order->fresh(), $escrow];
}

function buyerUrl(Escrow $escrow, ?string $slug = null): string
{
    return '/'.($slug ?? $escrow->storePage->slug).'/escrow/'.$escrow->releaseToken();
}

beforeEach(function () {
    config(['mypage.features.escrow' => false]);
    Feature::flush();
});

it('shows the buyer their held payment with a working link', function () {
    [$seller, $order, $escrow] = escrowedOrder(100000);

    // The held figure is what the seller is owed, so it is net of commission:
    // ₦1,000 less 150bps is ₦985. Asserted off the row so it cannot drift.
    get(buyerUrl($escrow))
        ->assertOk()
        ->assertSee('Your payment is protected')
        ->assertSee(Money::format($escrow->amount_kobo))
        // The release button is the whole point of the page.
        ->assertSee('I got it')
        ->assertSee($order->reference);
});

it('never opens an escrow to a guessed token', function () {
    escrowedOrder();

    get('/some-shop/escrow/'.str_repeat('a', 24))->assertNotFound();
});

it('never opens one seller\'s escrow on another seller\'s page', function () {
    [, , $escrow] = escrowedOrder();

    $other = StorePage::factory()->create(['slug' => 'someone-else']);

    // The token is valid, but the store page does not match, so it 404s rather
    // than leaking one seller's buyer to another's storefront.
    get(buyerUrl($escrow, $other->slug))->assertNotFound();
});

it('releases the money to the seller when the buyer confirms', function () {
    [$seller, , $escrow] = escrowedOrder(100000);

    $held = Wallet::platform(WalletKind::Escrow)->balance_kobo;
    expect($held)->toBeGreaterThan(0);

    post(buyerUrl($escrow).'/release')->assertRedirect();

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Released)
        ->and(Wallet::forSeller($seller->id)->balance_kobo)->toBeGreaterThan(0)
        ->and(Wallet::platform(WalletKind::Escrow)->balance_kobo)->toBe(0);
});

it('will not pay the seller twice if the buyer clicks twice', function () {
    [$seller, , $escrow] = escrowedOrder(100000);

    post(buyerUrl($escrow).'/release');
    $afterFirst = Wallet::forSeller($seller->id)->balance_kobo;

    post(buyerUrl($escrow).'/release')->assertRedirect();

    expect(Wallet::forSeller($seller->id)->balance_kobo)->toBe($afterFirst)
        ->and($escrow->fresh()->status)->toBe(EscrowStatus::Released);
});

it('freezes the money when the buyer disputes', function () {
    [, , $escrow] = escrowedOrder(100000);

    post(buyerUrl($escrow).'/dispute', [
        'reason' => 'I ordered two Ankara sets and only one ever arrived.',
    ])->assertRedirect();

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Frozen)
        ->and($escrow->fresh()->dispute_reason)->toContain('Ankara');
});

it('does not blame the seller for a buyer\'s dispute', function () {
    [, , $escrow] = escrowedOrder();

    post(buyerUrl($escrow).'/dispute', ['reason' => 'The order never arrived at my address.']);

    // disputed_by_seller_id stays null: a seller must never appear to have
    // complained about their own customer.
    expect($escrow->fresh()->disputed_by_seller_id)->toBeNull()
        ->and($escrow->fresh()->status)->toBe(EscrowStatus::Frozen);
});

it('insists on a usable reason before freezing', function () {
    [, , $escrow] = escrowedOrder();

    post(buyerUrl($escrow).'/dispute', ['reason' => 'bad'])->assertSessionHasErrors('reason');
    post(buyerUrl($escrow).'/dispute', ['reason' => ''])->assertSessionHasErrors('reason');

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Held);
});

it('refuses to release money that is already frozen', function () {
    [, , $escrow] = escrowedOrder();

    post(buyerUrl($escrow).'/dispute', ['reason' => 'The goods never turned up at all.']);
    expect($escrow->fresh()->status)->toBe(EscrowStatus::Frozen);

    post(buyerUrl($escrow).'/release')->assertRedirect();

    // A dispute has to end in an admin decision, not in the buyer changing
    // their mind an hour later.
    expect($escrow->fresh()->status)->toBe(EscrowStatus::Frozen);
});

it('lets the seller freeze their own buyer\'s money', function () {
    [$seller, $order, $escrow] = escrowedOrder();

    actingAs($seller)->post(route('dashboard.orders.escrow-dispute', $order), [
        'reason' => 'The courier says delivered, the buyer says it never arrived.',
    ])->assertRedirect();

    expect($escrow->fresh()->status)->toBe(EscrowStatus::Frozen)
        ->and($escrow->fresh()->disputed_by_seller_id)->toBe($seller->id);
});

it('stops a seller disputing somebody else\'s order', function () {
    [$seller, $order] = escrowedOrder();

    $intruder = Seller::factory()->create();

    actingAs($intruder)
        ->post(route('dashboard.orders.escrow-dispute', $order), ['reason' => 'A perfectly reasonable reason.'])
        ->assertNotFound();
});

it('tells a seller they cannot dispute an order that has no escrow', function () {
    [$seller, , $product] = sellerWithProduct();

    $order = Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $product->storePage->id,
    ]);

    actingAs($seller)
        ->post(route('dashboard.orders.escrow-dispute', $order), ['reason' => 'Something went wrong here.'])
        ->assertSessionHasErrors('reason');
});

it('shows the seller the buyer\'s release link to pass along', function () {
    [$seller, $order] = escrowedOrder();

    actingAs($seller)
        ->get(route('dashboard.orders.show', $order))
        ->assertOk()
        ->assertSee('Escrow')
        ->assertSee($order->escrow->releaseToken());
});

it('reaches the escrow page from order tracking, but only with the right details', function () {
    [$seller, $order] = escrowedOrder();
    $slug = $order->storePage->slug;

    // Reference alone is not enough: the buyer must also prove the contact.
    get("/{$slug}/track-order?reference={$order->reference}")
        ->assertOk()
        ->assertDontSee('being held safely');

    get("/{$slug}/track-order?reference={$order->reference}&contact={$order->customer_email}")
        ->assertOk()
        ->assertSee('being held safely')
        ->assertSee($order->escrow->releaseToken());
});

it('stops one buyer seeing another buyer\'s escrow link', function () {
    [$seller, $order] = escrowedOrder();
    $slug = $order->storePage->slug;

    get("/{$slug}/track-order?reference={$order->reference}&contact=someone.else@example.com")
        ->assertOk()
        ->assertDontSee('being held safely');
});
