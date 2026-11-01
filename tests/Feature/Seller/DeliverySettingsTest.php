<?php

use App\Domains\Seller\Models\DeliveryProfile;
use App\Domains\Seller\Models\DeliveryZone;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{0: Seller, 1: StorePage}
 */
function deliveryPage(string $theme = 'store'): array
{
    $seller = Seller::factory()->create();

    $page = StorePage::factory()->for($seller)->withContent()->create([
        'theme' => $theme,
        'status' => 'published',
        'slug' => $theme === 'store' ? 'delivery-shop' : 'delivery-studio',
    ]);

    return [$seller, $page];
}

it('saves the store delivery profile and its areas', function () {
    [$seller, $page] = deliveryPage();

    actingAs($seller)->post('/storefront/delivery', [
        'offers_delivery' => '1',
        'offers_pickup' => '1',
        'offers_home_service' => '1',
        'free_over' => '50000',
        'radius_km' => 20,
        'notes' => 'Same-day in Lagos',
        'zones' => [
            ['name' => 'Ikeja', 'state' => 'Lagos', 'fee' => '1500'],
            // A blank area is not a place the seller delivers to.
            ['name' => '', 'state' => 'Lagos', 'fee' => '2000'],
            ['name' => 'Yaba', 'state' => 'Lagos', 'fee' => '0'],
        ],
    ])->assertRedirect();

    $profile = $page->fresh()->deliveryProfile()->with('zones')->firstOrFail();

    expect($profile->offers_delivery)->toBeTrue()
        ->and($profile->offers_pickup)->toBeTrue()
        ->and($profile->offers_home_service)->toBeTrue()
        ->and($profile->free_over_kobo)->toBe(5_000_000)
        ->and($profile->radius_km)->toBe(20)
        ->and($profile->notes)->toBe('Same-day in Lagos')
        ->and($profile->zones->pluck('name')->all())->toBe(['Ikeja', 'Yaba'])
        ->and($profile->zones->pluck('fee_kobo')->all())->toBe([150_000, 0]);
});

it('replaces the area list instead of appending to it', function () {
    [$seller, $page] = deliveryPage();

    actingAs($seller)->post('/storefront/delivery', [
        'zones' => [['name' => 'Ikeja', 'state' => 'Lagos', 'fee' => '1000']],
    ]);

    actingAs($seller)->post('/storefront/delivery', [
        'offers_pickup' => '1',
        'zones' => [['name' => 'Surulere', 'state' => 'Lagos', 'fee' => '1500']],
    ]);

    $profile = $page->fresh()->deliveryProfile()->with('zones')->firstOrFail();

    expect($profile->zones)->toHaveCount(1)
        ->and($profile->zones->first()->name)->toBe('Surulere')
        ->and($profile->offers_delivery)->toBeFalse()
        ->and($profile->offers_pickup)->toBeTrue();
});

it('shows the delivery tab with the saved profile', function () {
    [$seller, $page] = deliveryPage();

    DeliveryProfile::factory()->for($page)->freeOver(5_000_000)->create();

    actingAs($seller)->get('/storefront?tab=delivery')
        ->assertOk()
        ->assertSee('Delivery &amp; pickup', false)
        ->assertSee('Delivery areas')
        ->assertSee('50000.00');
});

it('shows delivery facts on the storefront home', function () {
    [, $page] = deliveryPage();

    $profile = DeliveryProfile::factory()->for($page)->freeOver(5_000_000)->create([
        'notes' => 'Order before noon',
    ]);

    DeliveryZone::factory()->create([
        'delivery_profile_id' => $profile->id,
        'name' => 'Ikeja',
        'state' => 'Lagos',
        'fee_kobo' => 150_000,
    ]);

    get(storeUrl($page->slug, ''))->assertOk()
        ->assertSee('Delivered to your door')
        ->assertSee('Free over ₦50,000')
        ->assertSee('1 delivery area')
        ->assertSee('Ikeja')
        ->assertSee('Order before noon');
});

it('keeps the band off the page until delivery is set up', function () {
    [, $page] = deliveryPage();

    get(storeUrl($page->slug, ''))->assertOk()
        ->assertDontSee('Delivered to your door')
        ->assertDontSee('Pickup available');
});

it('does not promise delivery when both switches are off', function () {
    [, $page] = deliveryPage();

    DeliveryProfile::factory()->for($page)->create([
        'offers_delivery' => false,
        'offers_pickup' => false,
    ]);

    get(storeUrl($page->slug, ''))->assertOk()
        ->assertDontSee('Delivered to your door')
        ->assertDontSee('Pickup available');
});

it('shows home service facts on a service storefront', function () {
    [, $page] = deliveryPage('service');

    $profile = DeliveryProfile::factory()->for($page)->create([
        'offers_delivery' => false,
        'offers_home_service' => true,
        'radius_km' => 15,
    ]);

    DeliveryZone::factory()->create([
        'delivery_profile_id' => $profile->id,
        'name' => 'Lekki',
        'state' => 'Lagos',
    ]);

    get(storeUrl($page->slug, '/'))->assertOk()
        ->assertSee('We come to you')
        ->assertSee('Home service within 15 km')
        ->assertSee('Lekki');
});
