<?php

use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Catalog\Models\Product;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Middleware\EnsureSellerIsVerified;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * The seller dashboard needs two verifications and used to claim one.
 *
 * 'verified' is Laravel's own alias and always worked: it sends a seller with an
 * unconfirmed email address to /email/verify. What the routes implied, and never
 * did, was the second one — an admin looking at a CAC certificate or a national
 * ID. Those tests cover the gap that opened, and the loop that waits for anyone
 * who wires the gate up without exempting the screen it redirects to.
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

it('turns an unverified seller away from the dashboard', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->get(route('dashboard'))
        ->assertRedirect(route('badge.apply'))
        ->assertSessionHas('status');
});

it('turns an unverified seller away from the rest of the dashboard, not just the front page', function () {
    [$seller] = unverifiedSeller();

    foreach (['dashboard.orders.index', 'customers.index', 'reviews.index', 'products.index'] as $name) {
        actingAs($seller)
            ->get(route($name))
            ->assertRedirect(route('badge.apply'), $name.' was reachable without verification');
    }
});

it('refuses the write, not just the screen', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->post(route('page.publish'))
        ->assertRedirect(route('badge.apply'));

    // The gate has to run before the controller, or the redirect is theatre.
    expect($seller->storePages()->first()->status)->toBe(StoreStatus::Draft);
});

it('refuses a product created by an unverified seller', function () {
    [$seller, $page] = unverifiedSeller();

    actingAs($seller)->post(route('products.store'), ['name' => 'Ankara', 'price_kobo' => 250000]);

    expect(Product::where('store_page_id', $page->getKey())->count())->toBe(0);
});

it('refuses a payment key from an unverified seller', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->get(route('payments.gateways.index'))
        ->assertRedirect(route('badge.apply'));

    actingAs($seller)
        ->post(route('payments.gateways.store'), ['gateway' => 'paystack', 'secret_key' => 'sk_test_x'])
        ->assertRedirect(route('badge.apply'));

    expect(SellerGateway::withoutGlobalScopes()->count())->toBe(0);
});

it('answers a JSON caller with a refusal rather than a redirect to a page', function () {
    [$seller] = unverifiedSeller();

    actingAs($seller)
        ->getJson(route('dashboard'))
        ->assertForbidden();
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
