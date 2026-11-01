<?php

use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Middleware\EnsureSellerIsVerified;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * Business verification is a buyer-facing trust signal, not a dashboard gate.
 *
 * 'verified' is Laravel's own alias and still guards the dashboard: it sends a
 * seller with an unconfirmed email address to /email/verify. An admin looking at
 * a CAC certificate or a national ID stamps the page so buyers can see the
 * badge, but it must not lock a seller out of the tools they need to run their
 * shop.
 */

/**
 * An unverified seller. Returns [seller, page].
 *
 * @return array{0: Seller, 1: StorePage}
 */
function unverifiedSeller(array $attributes = []): array
{
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->unverified()->create($attributes);

    return [$seller, $page];
}

it('lets an unverified seller use the dashboard', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->get(route('dashboard'))
        ->assertOk();
});

it('lets an unverified seller use the rest of the dashboard, not just the front page', function () {
    [$seller] = unverifiedSeller();

    foreach (['dashboard.orders.index', 'customers.index', 'reviews.index', 'products.index'] as $name) {
        actingAs($seller)
            ->get(route($name))
            ->assertOk($name.' was blocked by business verification');
    }
});

it('lets an unverified seller attempt a write', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->post(route('page.publish'))
        ->assertRedirect(route('dashboard'));

    // The publish blockers still apply; business verification does not.
    expect($seller->storePages()->first()->status)->toBe(StoreStatus::Draft);
});

it('lets an unverified seller open the product form', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->get(route('products.create'))
        ->assertOk();
});

it('lets an unverified seller open payment settings', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->get(route('payments.gateways.index'))
        ->assertOk();
});

it('answers a JSON caller without a verification redirect', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->getJson(route('dashboard'))
        ->assertOk();
});

it('lets the badge screen itself through, or the blocked seller loops forever', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->get(route('badge.apply'))
        ->assertOk()
        ->assertSee('Verify your business');
});

it('lets onboarding through, or a seller can never sign up', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->get(route('onboarding.show', 'business'))->assertOk();
});

it('leaves a seller with no store page to the onboarding middleware', function () {
    $seller = Seller::factory()->create();

    // onboarding.complete owns this state. Verification must not pre-empt it with
    // a redirect to a badge screen that would itself 404 without a page.
    actingAs($seller)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show', 'business'));
});

it('tells a seller whose application is already queued that it is waiting, not that they must apply again', function () {
    [$seller, $page] = unverifiedSeller();

    BadgeApplication::factory()->create([
        'seller_id' => $seller->getKey(),
        'store_page_id' => $page->getKey(),
    ]);

    actingAs($seller)
        ->get(route('badge.apply'))
        ->assertOk()
        ->assertSee('Waiting on us');
});

it('lets a verified seller through', function () {
    [$seller] = unverifiedSeller();
    $seller->storePages()->first()->forceFill(['verified_at' => now()])->save();

    actingAs($seller)->get(route('dashboard'))->assertOk();
});

it('keeps the email check that was already there, alongside the new one', function () {
    $seller = Seller::factory()->unverified()->create();
    $page = StorePage::factory()->for($seller)->unverified()->create();

    // Business verification must not quietly replace the email gate. An
    // unconfirmed address is the earlier question and still gets asked first.
    actingAs($seller)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));

    expect($page->getKey())->not->toBeNull();
});

it('asks for the email before it asks for the business', function () {
    $seller = Seller::factory()->unverified()->create();
    StorePage::factory()->for($seller)->unverified()->create();

    actingAs($seller)
        ->post(route('page.publish'))
        ->assertRedirect(route('verification.notice'));

    expect($seller->storePages()->first()->status)->toBe(StoreStatus::Draft);
});

it('registers business verification under a name of its own', function () {
    $aliases = app(Kernel::class)->getMiddlewareAliases();

    expect($aliases['business.verified'] ?? null)->toBe(EnsureSellerIsVerified::class)
        // Laravel's own alias is untouched: overwriting it would have silently
        // dropped email verification from every route that relied on it.
        ->and($aliases['verified'])->toBe(EnsureEmailIsVerified::class);
});

it('leaves no dashboard route claiming a gate that does not exist', function () {
    $aliases = app(Kernel::class)->getMiddlewareAliases();

    $ungated = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('business.verified', $route->gatherMiddleware(), true))
        ->reject(fn ($route) => isset($aliases['business.verified']))
        ->map(fn ($route) => $route->getName())
        ->values();

    // With no alias registered this is every gated route in the application —
    // the exact shape of the defect, asserted on so it cannot come back.
    expect($ungated)->toBeEmpty();
});
