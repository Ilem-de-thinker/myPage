<?php

use App\Domains\Billing\Models\Plan;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The landing page is the first thing anyone sees, and buyers must never be
 * pushed towards a sign-in form they do not need.
 *
 * The copy asserted here is the new_ui design's. What each test protects is the
 * behaviour underneath it — no sign-in wall, no dead pricing, a way back to the
 * dashboard — not the particular sentence that carries it.
 */
it('shows the platform without asking anyone to sign in', function () {
    get('/')
        ->assertOk()
        ->assertSee('Find the place')
        ->assertSee('Zero buyer accounts');
});

it('makes clear that browsing needs no account', function () {
    $html = get('/')->assertOk()->getContent();

    expect($html)->toContain('Zero buyer accounts')
        ->toContain('Zero marketplace commission');
});

it('never puts the sign in link where the start-selling call to action is', function () {
    $html = get('/')->assertOk()->getContent();

    // "Claim Your Storefront Handle" is the primary action. A visitor who is
    // not signed in must not be shown a way in at all: the only route into the
    // platform from here is registering.
    expect($html)->toContain(route('register'))
        ->not->toContain(route('login'));
});

it('links to both the store and service demos', function () {
    get('/')
        ->assertOk()
        ->assertSee('Retail Store Demo')
        ->assertSee('Service Booking Demo')
        ->assertSee(route('directory.products'))
        ->assertSee(route('directory.services'));
});

it('shows prices even before PlanSeeder has ever run', function () {
    // A deploy that forgot to seed must still show a price: a blank pricing
    // page is the worst possible first impression.
    expect(Plan::count())->toBe(0);

    get('/')
        ->assertOk()
        ->assertSee(config('billing.plans.growth.name'))
        ->assertSee('₦9,500')
        ->assertSee('Flat plans. No transaction tax.');
});

it('prefers a price stored in the database over the config', function () {
    Plan::factory()->create(['name' => 'Founder Rate', 'price_kobo' => 100000, 'is_featured' => true]);

    get('/')
        ->assertOk()
        ->assertSee('Founder Rate')
        ->assertSee('₦1,000')
        ->assertDontSee(config('billing.plans.growth.description'));
});

it('sends a signed in seller to their dashboard instead of a sign in form', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->get('/')
        ->assertOk()
        ->assertSee('Your dashboard')
        ->assertSee(route('dashboard'));
});

it('offers one sign in door on a storefront, in the footer', function () {
    $page = StorePage::factory()
        ->create(['status' => 'published', 'slug' => 'footer-link']);

    $html = get(storeUrl('footer-link'))->assertOk()->getContent();

    // The only way in is the footer link, so a buyer is never asked to log in.
    expect($html)->toContain('Sell on mypage.ng')
        ->toContain(route('login'))
        ->not->toContain(route('register'));
});

it('points the storefront footer at the dashboard once the seller signs in', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published', 'slug' => 'owner-footer']);

    actingAs($seller)->get(storeUrl('owner-footer'))
        ->assertOk()
        ->assertSee('Manage your page')
        ->assertDontSee('Sell on mypage.ng');
});
