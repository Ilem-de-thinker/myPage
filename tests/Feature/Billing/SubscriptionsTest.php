<?php

use App\Domains\Billing\Actions\ConfirmSubscriptionPayment;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Jobs\ExpireSubscriptions;
use App\Domains\Billing\Models\FeatureFlag;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\Feature;
use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Models\WalletLedger;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config([
        'services.paystack.secret' => 'sk_test_secret',
        'mypage.gateway' => 'paystack',
        'billing.grace_days' => 7,
    ]);

    $this->plan = Plan::factory()->create([
        'slug' => 'growth',
        'name' => 'Growth',
        'price_kobo' => 950000,
        'trial_days' => 14,
    ]);
});

it('seeds plans from config rather than hard coding prices', function () {
    $this->artisan('db:seed', ['--class' => 'PlanSeeder'])->assertSuccessful();

    foreach (config('billing.plans') as $slug => $definition) {
        expect(Plan::where('slug', $slug)->sole()->price_kobo)->toBe($definition['price_kobo']);
    }

    // Re-running updates rather than duplicating.
    $this->artisan('db:seed', ['--class' => 'PlanSeeder'])->assertSuccessful();

    expect(Plan::count())->toBe(count(config('billing.plans')));
});

it('starts a trial without pretending the money has arrived', function () {
    [$seller] = sellerWithPage();
    $manager = app(SubscriptionManager::class);

    $subscription = $manager->start($seller, $this->plan, 'gateway');

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at)->not->toBeNull()
        ->and($subscription->current_period_ends_at)->toBeNull()
        ->and($subscription->canWrite())->toBeTrue();
});

it('renews a subscription and rolls the period forward', function () {
    [$seller] = sellerWithPage();
    $manager = app(SubscriptionManager::class);

    $subscription = $manager->start($seller, $this->plan, 'manual');
    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    $oldEnd = $subscription->current_period_ends_at;

    $this->travel(30)->days();

    $renewed = $manager->renew($subscription->fresh());

    expect($renewed->status)->toBe(SubscriptionStatus::Active)
        ->and($renewed->current_period_ends_at->isAfter($oldEnd))->toBeTrue();
});

it('keeps a cancelled seller selling until the period they paid for ends', function () {
    [$seller] = sellerWithPage();
    $manager = app(SubscriptionManager::class);

    // A paid plan, not a trial: a trial is not a period they have paid for.
    $plan = Plan::factory()->withoutTrial()->create();
    $subscription = $manager->start($seller, $plan, 'manual');

    $cancelled = $manager->cancel($subscription);

    expect($cancelled->status)->toBe(SubscriptionStatus::Active)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->canWrite())->toBeTrue();

    // Once the paid period is over it really is finished.
    $this->travel(40)->days();

    expect($subscription->fresh()->canWrite())->toBeFalse();
});

it('gives a failed payment a grace period before anything is restricted', function () {
    [$seller] = sellerWithPage();
    $manager = app(SubscriptionManager::class);

    $subscription = $manager->start($seller, $this->plan, 'gateway');

    $pastDue = $manager->markPastDue($subscription);

    expect($pastDue->status)->toBe(SubscriptionStatus::PastDue)
        ->and($pastDue->isInGrace())->toBeTrue()
        // Grace is exactly the point: the seller keeps working.
        ->and($pastDue->canWrite())->toBeTrue();

    $this->travel(8)->days();

    expect($subscription->fresh()->canWrite())->toBeFalse();
});

it('expires subscriptions only after the grace period is spent', function () {
    [$seller] = sellerWithPage();
    $manager = app(SubscriptionManager::class);

    $withinGrace = $manager->markPastDue($manager->start($seller, $this->plan, 'gateway'));

    app(ExpireSubscriptions::class)->handle();

    expect($withinGrace->fresh()->status)->toBe(SubscriptionStatus::PastDue);

    $this->travel(8)->days();
    app(ExpireSubscriptions::class)->handle();

    expect($withinGrace->fresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('closes a subscription that ends its trial and is never paid for', function () {
    [$seller] = sellerWithPage();
    $manager = app(SubscriptionManager::class);

    $trial = $manager->start($seller, $this->plan, 'gateway');

    app(ExpireSubscriptions::class)->handle();

    // The trial is still running, so nothing has happened yet.
    expect($trial->fresh()->status)->toBe(SubscriptionStatus::Trialing);

    $this->travel(30)->days();
    app(ExpireSubscriptions::class)->handle();

    expect($trial->fresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('confirms a subscription payment and renews it', function () {
    [$seller] = sellerWithPage();

    fakePaystack();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth'])
        ->assertRedirect('https://checkout.paystack.com/test-checkout');

    $subscription = Subscription::where('seller_id', $seller->id)->sole();
    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    // Started as unpaid: an abandoned checkout never leaves a paying seller.
    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($transaction->amount_kobo)->toBe(950000);

    app(ConfirmSubscriptionPayment::class)->handle($transaction->reference);

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Paid)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('never credits a subscribing seller from their own payment', function () {
    [$seller] = sellerWithPage();

    fakePaystack();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth']);

    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    app(ConfirmSubscriptionPayment::class)->handle($transaction->reference);

    // A subscription is the seller paying us, so no sale is settled into their
    // wallet the way a customer order would be.
    expect(WalletLedger::withoutGlobalScopes()->count())->toBe(0)
        ->and(Wallet::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an underpaid subscription charge', function () {
    [$seller] = sellerWithPage();

    fakePaystack(['amount_kobo' => 1]);

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth']);

    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    expect(app(ConfirmSubscriptionPayment::class)->handle($transaction->reference))->toBeNull()
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Failed)
        ->and(Subscription::where('seller_id', $seller->id)->sole()->status)->toBe(SubscriptionStatus::Trialing);
});

it('cannot renew twice from a replayed webhook', function () {
    [$seller] = sellerWithPage();

    fakePaystack();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth']);

    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    app(ConfirmSubscriptionPayment::class)->handle($transaction->reference);
    $firstEnd = Subscription::where('seller_id', $seller->id)->sole()->current_period_ends_at;

    $this->travel(5)->days();
    app(ConfirmSubscriptionPayment::class)->handle($transaction->reference);

    // Still the same period: the second call short-circuits.
    expect(Subscription::where('seller_id', $seller->id)->sole()->current_period_ends_at->equalTo($firstEnd))->toBeTrue();
});

it('shows the billing page with plans and the current subscription', function () {
    [$seller] = sellerWithPage();
    app(SubscriptionManager::class)->start($seller, $this->plan, 'gateway');

    actingAs($seller)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Growth')
        ->assertSee('₦9,500')
        ->assertSee('Free trial')
        ->assertSee('Cancel subscription');
});

it('explains a failed payment without taking the page offline', function () {
    [$seller] = sellerWithPage();

    $page = $seller->storePages()->sole();
    $page->update(['status' => 'published', 'slug' => 'openstill']);
    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);

    $subscription = app(SubscriptionManager::class)->start($seller, $this->plan, 'gateway');
    app(SubscriptionManager::class)->markPastDue($subscription);

    // The storefront is never gated on billing.
    get('/openstill')->assertOk();

    actingAs($seller)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('did not go through')
        ->assertSee('Your page stays online');
});

it('records a manual transfer and keeps the reference for approval', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->post(route('billing.manual'), [
        'plan' => 'growth',
        'reference' => 'TRF/99281',
    ])->assertRedirect(route('billing.index'));

    $subscription = Subscription::where('seller_id', $seller->id)->sole();

    expect($subscription->payment_method)->toBe('manual')
        ->and($subscription->gateway_reference)->toBe('TRF/99281');
});

it('never shows one seller another seller\'s subscription', function () {
    [$seller] = sellerWithPage();
    [$otherSeller] = sellerWithPage();

    $other = Subscription::factory()->forSeller($otherSeller)->create(['plan_id' => $this->plan->id]);

    actingAs($seller)->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee($other->gateway_reference);

    actingAs($seller)->post(route('billing.cancel'))->assertNotFound();

    expect($other->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('ships features dark and lets a database row switch one on for a seller', function () {
    config(['mypage.features.escrow' => false]);
    Feature::flush();

    [$seller] = sellerWithPage();

    expect(Feature::enabled('escrow', $seller->id))->toBeFalse();

    config(['mypage.features.escrow' => true]);
    Feature::flush();

    expect(Feature::enabled('escrow', $seller->id))->toBeTrue();

    // A row narrows it for one seller without touching anybody else.
    FeatureFlag::create(['seller_id' => $seller->id, 'feature' => 'escrow', 'enabled' => false]);
    Feature::flush();

    expect(Feature::enabled('escrow', $seller->id))->toBeFalse();
});

it('treats a feature missing from config as off', function () {
    config(['mypage.features' => []]);
    Feature::flush();

    [$seller] = sellerWithPage();

    expect(Feature::enabled('a_feature_nobody_configured', $seller->id))->toBeFalse();
});

it('blocks dashboard writes for a lapsed seller but still lets them read and pay', function () {
    [$seller] = sellerWithPage();
    fakePaystack();
    $page = $seller->storePages()->sole();

    $subscription = Subscription::factory()->forSeller($seller)
        ->create(['plan_id' => $this->plan->id, 'status' => SubscriptionStatus::Expired]);

    // Reading is always allowed: they must be able to see what they have.
    actingAs($seller)->get(route('dashboard'))->assertOk();
    actingAs($seller)->get(route('products.index'))->assertOk();
    actingAs($seller)->get(route('billing.index'))->assertOk();

    // Writing is not, and they land on the page that explains it.
    actingAs($seller)
        ->post(route('products.store'), ['name' => 'Sneaky', 'duration_min' => 30])
        ->assertRedirect(route('billing.index'));

    // And they can pay, because that is the whole point: subscribing replaces
    // the lapsed record rather than leaving them stuck.
    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth'])->assertRedirect();

    expect($subscription->fresh()->status)->not->toBe(SubscriptionStatus::Expired)
        ->and(Subscription::where('seller_id', $seller->id)->sole()->canWrite())->toBeTrue();

    // With a live subscription they are writing again.
    actingAs($seller)
        ->post(route('products.store'), ['name' => 'Sneaky', 'duration_min' => 30])
        ->assertRedirect();
});
