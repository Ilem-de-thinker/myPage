<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Billing\Enums\MerchantStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\GracePeriod;
use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = Seller::factory()->admin()->create(['email_verified_at' => now()]);
    $this->plan = Plan::factory()->create(['slug' => 'growth']);
    [$this->seller, $this->page] = sellerWithPage([
        'status' => 'published',
        'slug' => 'overridden-shop',
    ]);

    $this->subscription = Subscription::factory()
        ->forSeller($this->seller)
        ->create([
            'plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => now()->subDay(),
            'current_period_ends_at' => now()->subDays(5),
        ]);
});

it('lets an admin extend one merchant grace period and audits it', function () {
    actingAs($this->admin)->post(route('admin.stores.grace', $this->page), [
        'grace_until' => now()->addDays(6)->format('Y-m-d H:i:s'),
    ])->assertRedirect();

    $subscription = $this->subscription->fresh();

    expect($subscription->grace_override_until?->isFuture())->toBeTrue()
        ->and($subscription->merchantStatus())->toBe(MerchantStatus::Grace);

    $entry = AdminAuditLog::query()
        ->where('action', 'subscription.grace_override')
        ->where('subject_id', $subscription->id)
        ->sole();

    expect($entry->admin_seller_id)->toBe($this->admin->id)
        ->and($entry->note)->toContain('Grace extended');
});

it('lets an admin clear a grace override', function () {
    $this->subscription->update(['grace_override_until' => now()->addDays(6)]);

    actingAs($this->admin)->post(route('admin.stores.grace', $this->page), [
        'grace_until' => '',
    ])->assertRedirect();

    expect($this->subscription->fresh()->grace_override_until)->toBeNull();

    expect(AdminAuditLog::query()->where('action', 'subscription.grace_override')->latest('id')->sole()->note)
        ->toContain('cleared');
});

it('rejects a grace date in the past', function () {
    actingAs($this->admin)->post(route('admin.stores.grace', $this->page), [
        'grace_until' => now()->subDay()->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('grace_until');

    expect($this->subscription->fresh()->grace_override_until)->toBeNull();
});

it('does not let a non-admin touch grace', function () {
    actingAs($this->seller)->post(route('admin.stores.grace', $this->page), [
        'grace_until' => now()->addDays(6)->format('Y-m-d H:i:s'),
    ])->assertNotFound();

    expect($this->subscription->fresh()->grace_override_until)->toBeNull();
});

it('stores the platform default grace days from settings', function () {
    actingAs($this->admin)->post(route('admin.settings.update'), [
        'platform_name' => 'mypage.ng',
        'platform_tagline' => 'Sovereign storefronts.',
        'grace_days' => 3,
        'subscriptions_enabled' => '1',
        'store_theme_enabled' => '1',
        'service_theme_enabled' => '1',
    ])->assertRedirect();

    expect(PlatformSetting::value('billing.grace_days'))->toBe(3)
        ->and(app(GracePeriod::class)->days())->toBe(3);
});
