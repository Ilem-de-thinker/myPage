<?php

use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Billing\Actions\ResolveMerchantStatus;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Enums\MerchantStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Jobs\ExpireSubscriptions;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\SubscriptionGraceStarted;
use App\Domains\Billing\Services\GracePeriod;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    config([
        'billing.grace_days' => 7,
        'mypage.gateway' => 'paystack',
    ]);

    $this->plan = Plan::factory()->create(['slug' => 'growth']);
    [$this->seller, $this->page] = sellerWithPage([
        'status' => 'published',
        'slug' => 'grace-shop',
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function merchantSubscription(Seller $seller, Plan $plan, array $attributes = []): Subscription
{
    return Subscription::factory()
        ->forSeller($seller)
        ->create([
            'plan_id' => $plan->id,
            ...$attributes,
        ]);
}

it('treats a seller who predates billing as active', function () {
    expect(app(ResolveMerchantStatus::class)->handle(null))->toBe(MerchantStatus::Active);
});

it('reads an active paid period as active', function () {
    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Active,
        'current_period_ends_at' => now()->addDays(5),
    ]);

    expect($subscription->merchantStatus())->toBe(MerchantStatus::Active)
        ->and($subscription->canWrite())->toBeTrue();
});

it('gives a paid period that has run out a grace window', function () {
    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Active,
        'current_period_ends_at' => now()->subDay(),
    ]);

    expect($subscription->merchantStatus())->toBe(MerchantStatus::Grace)
        ->and($subscription->canWrite())->toBeTrue();

    $this->travel(8)->days();

    expect($subscription->fresh()->merchantStatus())->toBe(MerchantStatus::Expired)
        ->and($subscription->fresh()->canWrite())->toBeFalse();
});

it('turns a past-due row into grace only while the grace date is future', function () {
    $within = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDay(),
        'grace_ends_at' => now()->addDays(2),
    ]);

    $over = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDays(10),
        'grace_ends_at' => now()->subDay(),
    ]);

    expect($within->merchantStatus())->toBe(MerchantStatus::Grace)
        ->and($over->merchantStatus())->toBe(MerchantStatus::Expired);
});

it('lets an operator override extend one merchant without changing the default', function () {
    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDays(10),
        'grace_ends_at' => now()->subDay(),
        'grace_override_until' => now()->addDays(4),
    ]);

    expect($subscription->merchantStatus())->toBe(MerchantStatus::Grace)
        ->and($subscription->daysLeft())->toBe(4);
});

it('ends a trial immediately with no grace when its days are over', function () {
    $running = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(3),
        'current_period_ends_at' => null,
    ]);

    $justEnded = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subDay(),
        'current_period_ends_at' => null,
    ]);

    $longEnded = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subDays(9),
        'current_period_ends_at' => null,
    ]);

    expect($running->merchantStatus())->toBe(MerchantStatus::Active)
        ->and($justEnded->merchantStatus())->toBe(MerchantStatus::Expired)
        ->and($justEnded->canWrite())->toBeFalse()
        ->and($longEnded->merchantStatus())->toBe(MerchantStatus::Expired);
});

it('still lets an operator override extend an ended trial', function () {
    $subscription = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->subDay(),
        'current_period_ends_at' => null,
        'grace_override_until' => now()->addDays(3),
    ]);

    expect($subscription->merchantStatus())->toBe(MerchantStatus::Grace)
        ->and($subscription->canWrite())->toBeTrue();
});

it('keeps a cancelled seller active until the paid period ends and then cancels them', function () {
    $paidFor = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::Active,
        'cancelled_at' => now()->subDay(),
        'current_period_ends_at' => now()->addDays(3),
    ]);

    $periodOver = Subscription::factory()->forSeller($this->seller)->make([
        'plan_id' => $this->plan->id,
        'status' => SubscriptionStatus::Cancelled,
        'cancelled_at' => now()->subDays(10),
        'current_period_ends_at' => now()->subDays(5),
    ]);

    expect($paidFor->merchantStatus())->toBe(MerchantStatus::Active)
        ->and($periodOver->merchantStatus())->toBe(MerchantStatus::Cancelled)
        ->and($periodOver->canWrite())->toBeFalse();
});

it('lets an explicit override restore a cancelled merchant', function () {
    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Cancelled,
        'cancelled_at' => now()->subDays(10),
        'current_period_ends_at' => now()->subDays(5),
        'grace_override_until' => now()->addDays(2),
    ]);

    expect($subscription->merchantStatus())->toBe(MerchantStatus::Grace);
});

it('does not expire an overridden subscription during the nightly sweep', function () {
    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::PastDue,
        'grace_ends_at' => now()->subDay(),
        'grace_override_until' => now()->addDays(5),
    ]);

    app(ExpireSubscriptions::class)->handle();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->fresh()->canWrite())->toBeTrue();
});

it('uses the platform grace setting when one is stored', function () {
    PlatformSetting::put('billing.grace_days', 2, 'integer');

    expect(app(GracePeriod::class)->days())->toBe(2);

    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::PastDue,
        'current_period_ends_at' => now()->subDay(),
        'grace_ends_at' => now()->addDays(1),
    ]);

    expect($subscription->merchantStatus())->toBe(MerchantStatus::Grace);
});

it('keeps an expired storefront readable but blocks its commerce writes', function () {
    merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Expired,
        'current_period_ends_at' => now()->subDays(20),
    ]);

    get(storeUrl('grace-shop'))
        ->assertOk()
        ->assertSee('temporarily not accepting new orders');

    post(storeUrl('grace-shop', '/checkout'))
        ->assertStatus(402);

    post(storeUrl('grace-shop', '/book'))
        ->assertStatus(402);
});

it('still allows checkout during grace', function () {
    merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::PastDue,
        'grace_ends_at' => now()->addDays(3),
    ]);

    post(storeUrl('grace-shop', '/checkout'))
        ->assertStatus(302)
        ->assertSessionHasErrors();
});

it('does not gate the storefront when subscriptions are disabled', function () {
    PlatformSetting::put('billing.subscriptions.enabled', false, 'boolean');

    merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Expired,
        'current_period_ends_at' => now()->subDays(20),
    ]);

    get(storeUrl('grace-shop'))->assertOk()->assertDontSee('temporarily not accepting new orders');

    post(storeUrl('grace-shop', '/checkout'))
        ->assertStatus(302)
        ->assertSessionHasErrors();
});

it('emails the seller once when grace starts', function () {
    Notification::fake();

    $subscription = merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Active,
        'current_period_ends_at' => now()->subDay(),
    ]);

    app(SubscriptionManager::class)->markPastDue($subscription);
    app(SubscriptionManager::class)->markPastDue($subscription->fresh());

    Notification::assertSentToTimes($this->seller, SubscriptionGraceStarted::class, 1);
});

it('shows the merchant their status on the dashboard', function () {
    merchantSubscription($this->seller, $this->plan, [
        'status' => SubscriptionStatus::Expired,
        'current_period_ends_at' => now()->subDays(20),
    ]);

    actingAs($this->seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('read-only');
});
