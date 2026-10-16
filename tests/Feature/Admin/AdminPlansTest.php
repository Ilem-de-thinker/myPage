<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Billing\Models\Plan;
use App\Domains\Seller\Models\Seller;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The plans panel exists so the price we quote and the price the gateway charges
 * cannot drift apart unnoticed. These tests hold that line.
 */
function planAdmin(): Seller
{
    return Seller::factory()->admin()->create(['email_verified_at' => now()]);
}

/**
 * The gateway's view of a plan, as GET /plan/:code would answer it.
 */
function fakeRemotePlan(int $amountKobo, string $interval = 'monthly', string $name = 'Growth'): void
{
    Http::fake([
        'api.paystack.co/plan/*' => Http::response([
            'status' => true,
            'data' => [
                'plan_code' => 'PLN_growth999',
                'name' => $name,
                'amount' => $amountKobo,
                'interval' => $interval,
            ],
        ]),
    ]);
}

beforeEach(function () {
    config([
        'services.paystack.secret' => 'sk_test_secret',
        'mypage.gateway' => 'paystack',
    ]);
});

it('creates the plan on the gateway and keeps its code', function () {
    $plan = Plan::factory()->create(['slug' => 'growth', 'name' => 'Growth', 'price_kobo' => 950000, 'interval' => 'monthly']);

    Http::fake(['api.paystack.co/plan' => Http::response([
        'status' => true,
        'data' => ['plan_code' => 'PLN_growth999', 'name' => 'Growth', 'amount' => 950000, 'interval' => 'monthly'],
    ])]);

    actingAs(planAdmin())->post(route('admin.plans.connect', $plan))->assertRedirect();

    expect($plan->fresh()->gateway_plan_code)->toBe('PLN_growth999');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/plan'
        && $request->data()['amount'] === 950000
        && $request->data()['interval'] === 'monthly');

    expect(AdminAuditLog::where('action', 'plan.gateway.create')->exists())->toBeTrue();
});

it('refuses to create a second plan for one that is already connected', function () {
    $plan = Plan::factory()->create(['gateway_plan_code' => 'PLN_existing']);

    Http::fake();

    actingAs(planAdmin())->post(route('admin.plans.connect', $plan))->assertSessionHasErrors('plan');

    Http::assertNothingSent();
    expect($plan->fresh()->gateway_plan_code)->toBe('PLN_existing');
});

it('connects nothing when the gateway cannot be reached', function () {
    $plan = Plan::factory()->create(['gateway_plan_code' => null]);

    Http::fake(['api.paystack.co/plan' => Http::failedConnection()]);

    actingAs(planAdmin())->post(route('admin.plans.connect', $plan))->assertSessionHasErrors('plan');

    expect($plan->fresh()->gateway_plan_code)->toBeNull();
});

it('shows a plan whose gateway price has drifted from ours', function () {
    $plan = Plan::factory()->create([
        'slug' => 'growth',
        'name' => 'Growth',
        'price_kobo' => 950000,
        'interval' => 'monthly',
        'gateway_plan_code' => 'PLN_growth999',
    ]);

    // We quote ₦9,500. The gateway charges ₦3,500.
    fakeRemotePlan(350000);

    actingAs(planAdmin())->get(route('admin.plans'))
        ->assertOk()
        ->assertSee('This plan is charging the wrong amount.')
        ->assertSee('3,500', escape: false); // ₦3,500 as the gateway has it
});

it('shows a plan as agreed when both sides match', function () {
    $plan = Plan::factory()->create([
        'slug' => 'growth',
        'name' => 'Growth',
        'price_kobo' => 950000,
        'interval' => 'monthly',
        'gateway_plan_code' => 'PLN_growth999',
    ]);

    fakeRemotePlan(950000);

    actingAs(planAdmin())->get(route('admin.plans'))
        ->assertOk()
        ->assertSee('Agrees with paystack.');
});

it('still renders the panel when the gateway cannot be read at all', function () {
    $plan = Plan::factory()->create(['gateway_plan_code' => 'PLN_growth999', 'price_kobo' => 950000]);

    Http::fake(['api.paystack.co/plan/*' => Http::failedConnection()]);

    // The local prices are still the ones being quoted, so the page has to come
    // up even when the gateway cannot confirm them.
    actingAs(planAdmin())->get(route('admin.plans'))
        ->assertOk()
        ->assertSee('Could not read this plan from paystack.');
});

it('changes the price on the gateway before recording it locally', function () {
    $plan = Plan::factory()->create([
        'slug' => 'growth',
        'name' => 'Growth',
        'price_kobo' => 950000,
        'interval' => 'monthly',
        'gateway_plan_code' => 'PLN_growth999',
    ]);

    Http::fake(['api.paystack.co/plan/*' => Http::response(['status' => true, 'message' => 'Plan updated. 0 subscription(s) affected'])]);

    actingAs(planAdmin())->post(route('admin.plans.price', $plan), ['price_kobo' => 1200000])
        ->assertRedirect();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->data()['amount'] === 1200000);

    expect($plan->fresh()->price_kobo)->toBe(1200000);

    expect(AdminAuditLog::where('action', 'plan.price.update')->exists())->toBeTrue();
});

it('never re-prices current subscribers unless asked', function () {
    $plan = Plan::factory()->create(['price_kobo' => 950000, 'gateway_plan_code' => 'PLN_growth999']);

    Http::fake(['api.paystack.co/plan/*' => Http::response(['status' => true, 'message' => 'ok'])]);

    actingAs(planAdmin())->post(route('admin.plans.price', $plan), ['price_kobo' => 1200000]);

    // Omitting the flag means "re-price everyone" at Paystack, so it is always
    // sent explicitly — and false unless the operator ticked the box.
    Http::assertSent(fn ($request) => $request->data()['update_existing_subscriptions'] === false);
});

it('re-prices existing subscribers when the operator explicitly asks', function () {
    $plan = Plan::factory()->create(['price_kobo' => 950000, 'gateway_plan_code' => 'PLN_growth999']);

    Http::fake(['api.paystack.co/plan/*' => Http::response(['status' => true, 'message' => 'Plan updated. 3 subscription(s) affected'])]);

    actingAs(planAdmin())->post(route('admin.plans.price', $plan), [
        'price_kobo' => 1200000,
        'update_existing' => '1',
    ])->assertSessionHas('status', fn (string $status) => str_contains($status, '3 existing subscription'));

    Http::assertSent(fn ($request) => $request->data()['update_existing_subscriptions'] === true);
});

it('leaves the price alone when the gateway refuses to change it', function () {
    $plan = Plan::factory()->create(['price_kobo' => 950000, 'gateway_plan_code' => 'PLN_growth999']);

    Http::fake(['api.paystack.co/plan/*' => Http::failedConnection()]);

    actingAs(planAdmin())->post(route('admin.plans.price', $plan), ['price_kobo' => 1200000])
        ->assertSessionHasErrors('plan');

    // The dangerous state is the panel quoting one price while the card charges
    // another, so a failed gateway call must not leave a new local price behind.
    expect($plan->fresh()->price_kobo)->toBe(950000);
});

it('lets a plan with no gateway code be re-priced before it is connected', function () {
    $plan = Plan::factory()->create(['price_kobo' => 950000, 'gateway_plan_code' => null]);

    Http::fake();

    actingAs(planAdmin())->post(route('admin.plans.price', $plan), ['price_kobo' => 1200000])
        ->assertRedirect();

    expect($plan->fresh()->price_kobo)->toBe(1200000);
});

it('refuses a price below the gateway minimum', function () {
    $plan = Plan::factory()->create(['price_kobo' => 950000, 'gateway_plan_code' => 'PLN_growth999']);

    // Paystack will not take a Naira transaction under ₦50.
    actingAs(planAdmin())->post(route('admin.plans.price', $plan), ['price_kobo' => 1000])
        ->assertSessionHasErrors('price_kobo');
});

it('does not mistake a no-op save for a change', function () {
    $plan = Plan::factory()->create(['price_kobo' => 950000, 'gateway_plan_code' => 'PLN_growth999']);

    Http::fake();

    actingAs(planAdmin())->post(route('admin.plans.price', $plan), ['price_kobo' => 950000])
        ->assertSessionHas('status', 'That is already the price.');

    Http::assertNothingSent();
});

it('is not open to a seller', function () {
    $plan = Plan::factory()->create();

    $seller = Seller::factory()->create();

    actingAs($seller)->get(route('admin.plans'))->assertNotFound();

    actingAs($seller)->post(route('admin.plans.price', $plan), ['price_kobo' => 1200000])->assertNotFound();

    expect($plan->fresh()->price_kobo)->not->toBe(1200000);
});

it('keeps an admin price across a re-seed', function () {
    config(['billing.plans' => ['growth' => [
        'name' => 'Growth',
        'price_kobo' => 950000,
        'interval' => 'monthly',
        'trial_days' => 14,
        'max_products' => 200,
        'description' => 'From config.',
    ]]]);

    $plan = Plan::factory()->create(['slug' => 'growth', 'price_kobo' => 1200000, 'interval' => 'monthly']);

    $this->seed(PlanSeeder::class);

    $plan->refresh();

    // The price a person set stays theirs; config only refreshes what it describes.
    expect($plan->price_kobo)->toBe(1200000)
        ->and($plan->description)->toBe('From config.')
        ->and($plan->max_products)->toBe(200);
});
