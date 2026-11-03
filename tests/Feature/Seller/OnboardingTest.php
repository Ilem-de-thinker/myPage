<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;

it('redirects new sellers into onboarding from the dashboard', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->get('/dashboard')->assertRedirect(route('onboarding.show', 'business'));
});

it('lets a seller skip setup and reach the dashboard', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->post(route('onboarding.skip'))
        ->assertRedirect(route('dashboard'));

    expect($seller->fresh()->onboarding_skipped_at)->not->toBeNull();

    actingAs($seller)->get('/dashboard')
        ->assertOk()
        ->assertSee('No page yet');

    actingAs($seller)->get('/orders')
        ->assertRedirect(route('onboarding.show', 'business'));
});

it('lets an admin through the dashboard without onboarding', function () {
    $admin = Seller::factory()->admin()->create();

    actingAs($admin)->get('/dashboard')->assertOk();
});

it('shows a friendly error when AI is unavailable during onboarding', function () {
    $seller = Seller::factory()->create();

    $this->mock(AiManager::class, function ($mock): void {
        $mock->shouldReceive('run')->andThrow(new RuntimeException('AI unavailable'));
    });

    actingAs($seller)
        ->from(route('onboarding.show', 'business'))
        ->post(route('onboarding.ai'), ['name' => 'Ada Bags', 'description' => 'Handmade bags'])
        ->assertRedirect(route('onboarding.show', 'business'))
        ->assertSessionHasErrors('description');
});

it('completes the wizard and creates a draft store page with sections', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->post('/onboarding/business', ['name' => 'Ada Shop', 'slug' => 'ada-shop'])
        ->assertRedirect(route('onboarding.show', 'theme'));

    actingAs($seller)->post('/onboarding/theme', ['theme' => 'service'])
        ->assertRedirect(route('onboarding.show', 'brand'));

    actingAs($seller)->post('/onboarding/brand', [
        'brand_color' => '#16a34a', 'whatsapp' => '0803 123 4567', 'city' => 'Lagos', 'state' => 'Lagos', 'bio' => 'Hair salon',
    ])->assertRedirect(route('onboarding.show', 'review'));

    actingAs($seller)->post('/onboarding/review')->assertRedirect(route('dashboard'));

    $page = StorePage::where('slug', 'ada-shop')->first();
    expect($page)->not->toBeNull()
        ->and($page->theme->value)->toBe('service')
        ->and($page->status->value)->toBe('draft')
        // Onboarding no longer writes an empty row for every block the theme
        // declares. A block with nothing in it does not render, so pre-creating
        // them would only hand a seller twenty-one blank boxes to fill in.
        ->and($page->blocks)->toHaveCount(0)
        ->and($page->whatsapp_number)->toBe('+2348031234567');
});

it('leaves a new store page blank for the seller to write', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->post('/onboarding/business', ['name' => 'Ada Store', 'slug' => 'ada-store']);
    actingAs($seller)->post('/onboarding/theme', ['theme' => 'store']);
    actingAs($seller)->post('/onboarding/brand', [
        'brand_color' => '#16a34a', 'whatsapp' => '0803 123 4567', 'city' => 'Lagos', 'state' => 'Lagos',
    ]);
    actingAs($seller)->post('/onboarding/review');

    expect(StorePage::where('slug', 'ada-store')->first()->blocks)->toHaveCount(0);
});

it('talks about a website once the seller has picked the service template', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->post('/onboarding/business', ['name' => 'Ada Braids', 'slug' => 'ada-braids']);
    actingAs($seller)->post('/onboarding/theme', ['theme' => 'service']);

    actingAs($seller)->get(route('onboarding.show', 'brand'))
        ->assertOk()
        ->assertSee('How your website looks')
        ->assertSee('where new booking requests will arrive')
        // A service seller is not opening a shop.
        ->assertDontSee('Short Store Bio');

    actingAs($seller)->get(route('onboarding.show', 'review'))
        ->assertOk()
        ->assertSee('Create my website')
        ->assertDontSee('Create my storefront');
});

it('still talks about a storefront for a store template', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->post('/onboarding/business', ['name' => 'Ada Store', 'slug' => 'ada-store']);
    actingAs($seller)->post('/onboarding/theme', ['theme' => 'store']);

    actingAs($seller)->get(route('onboarding.show', 'brand'))
        ->assertOk()
        ->assertSee('How your storefront looks')
        ->assertSee('where new orders will arrive')
        ->assertSee('Short Store Bio');

    actingAs($seller)->get(route('onboarding.show', 'review'))
        ->assertOk()
        ->assertSee('Create my storefront');
});

it('rejects reserved and duplicate slugs', function () {
    StorePage::factory()->create(['slug' => 'taken']);
    $seller = Seller::factory()->create();

    actingAs($seller)->post('/onboarding/business', ['name' => 'X', 'slug' => 'admin'])
        ->assertSessionHasErrors('slug');

    actingAs($seller)->post('/onboarding/business', ['name' => 'X', 'slug' => 'taken'])
        ->assertSessionHasErrors('slug');
});

it('shows the dashboard with the seller page only', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->published()->create(['name' => 'Ada Boutique']);

    actingAs($seller)->get('/dashboard')
        ->assertOk()
        ->assertSee('Ada Boutique');
});

it('blocks suspended sellers from dashboard routes', function () {
    $seller = Seller::factory()->suspended()->create();
    StorePage::factory()->for($seller)->create();

    actingAs($seller)->get('/dashboard')->assertSessionHasErrors('email');
});

it('treats a repeated review submission as already done instead of creating a second page', function () {
    [$seller, $page] = sellerWithPage();

    actingAs($seller)
        ->withSession(['onboarding' => ['name' => 'Another shop', 'slug' => 'another-shop', 'theme' => 'store']])
        ->post('/onboarding/review')
        ->assertRedirect(route('dashboard'));

    expect($seller->storePages()->count())->toBe(1);
});
