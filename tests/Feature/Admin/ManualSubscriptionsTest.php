<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;

/**
 * Manual bank transfers land on a core admin's desk. Approving is what makes
 * the plan real; the seller who typed a reference gets nothing until then.
 */
beforeEach(function () {
    $this->plan = Plan::factory()->withoutTrial()->create(['slug' => 'growth', 'price_kobo' => 950000]);
});

function coreAdmin(): Seller
{
    return Seller::factory()->admin()->create(['email_verified_at' => now()]);
}

function manualClaimFor(Seller $seller, Plan $plan): Subscription
{
    return app(SubscriptionManager::class)->start($seller, $plan, 'manual', 'TRF/'.fake()->unique()->numerify('#####'));
}

it('lists pending manual transfers for a core admin', function () {
    $seller = Seller::factory()->create();
    manualClaimFor($seller, $this->plan);

    actingAs(coreAdmin())->get(route('admin.manual-subscriptions'))
        ->assertOk()
        ->assertSee($seller->email)
        ->assertSee($this->plan->name);
});

it('grants nothing before the admin approves', function () {
    $seller = Seller::factory()->create();
    $subscription = manualClaimFor($seller, $this->plan);

    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->canWrite())->toBeFalse();
});

it('approves a manual transfer and activates the plan', function () {
    $seller = Seller::factory()->create();
    $subscription = manualClaimFor($seller, $this->plan);

    actingAs(coreAdmin())->post(route('admin.manual-subscriptions.approve', $subscription))
        ->assertRedirect();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->canWrite())->toBeTrue()
        ->and($subscription->fresh()->current_period_ends_at)->not->toBeNull();

    expect(AdminAuditLog::where('action', 'subscription.manual.approved')->count())->toBe(1);
});

it('approvals are idempotent for the same claim', function () {
    $seller = Seller::factory()->create();
    $subscription = manualClaimFor($seller, $this->plan);

    $admin = coreAdmin();

    actingAs($admin)->post(route('admin.manual-subscriptions.approve', $subscription));

    $ends = $subscription->fresh()->current_period_ends_at;

    actingAs($admin)->post(route('admin.manual-subscriptions.approve', $subscription));

    // Second approval is rejected: the subscription is no longer Pending.
    expect($subscription->fresh()->current_period_ends_at->equalTo($ends))->toBeTrue();
});

it('declines a manual transfer and grants nothing', function () {
    $seller = Seller::factory()->create();
    $subscription = manualClaimFor($seller, $this->plan);

    actingAs(coreAdmin())->post(route('admin.manual-subscriptions.decline', $subscription))
        ->assertRedirect();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->fresh()->canWrite())->toBeFalse();

    expect(AdminAuditLog::where('action', 'subscription.manual.declined')->count())->toBe(1);
});

it('hides the approval endpoint from a seller (404, panel does not exist for them)', function () {
    $seller = Seller::factory()->create();
    $subscription = manualClaimFor($seller, $this->plan);

    actingAs($seller)->post(route('admin.manual-subscriptions.approve', $subscription))
        ->assertNotFound();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Pending);
});
