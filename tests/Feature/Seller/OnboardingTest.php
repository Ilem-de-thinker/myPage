<?php

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;

it('redirects new sellers into onboarding from the dashboard', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->get('/dashboard')->assertRedirect(route('onboarding.show', 'business'));
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
        ->and($page->sections->where('page', 'home'))->toHaveCount(8)->and($page->sections)->toHaveCount(21)
        ->and($page->whatsapp_number)->toBe('+2348031234567');
});

it('seeds 7 sections for a store theme page', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->post('/onboarding/business', ['name' => 'Ada Store', 'slug' => 'ada-store']);
    actingAs($seller)->post('/onboarding/theme', ['theme' => 'store']);
    actingAs($seller)->post('/onboarding/brand', [
        'brand_color' => '#16a34a', 'whatsapp' => '0803 123 4567', 'city' => 'Lagos', 'state' => 'Lagos',
    ]);
    actingAs($seller)->post('/onboarding/review');

    expect(StorePage::where('slug', 'ada-store')->first()->sections->where('page', 'home'))->toHaveCount(7);
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
