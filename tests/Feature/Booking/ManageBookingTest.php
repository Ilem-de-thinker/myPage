<?php

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Shared\Support\StoreUrl;

use function Pest\Laravel\get;

it('lets a client find a booking with the reference and the contact they booked with', function () {
    [$seller, $page] = bookableServicePage();
    $service = Service::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Box Braids',
        'slug' => 'box-braids',
        'is_active' => true,
        'price_kobo' => 5500000,
    ]);

    $booking = Booking::factory()->create([
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'reference' => 'BK-8941',
        'customer_name' => 'Babatunde Adeleke',
        'customer_email' => 'baba@example.com',
        'customer_phone' => '+2348031234567',
        'status' => BookingStatus::Confirmed,
    ]);

    get(storeUrl($page->slug, '/book/manage?reference=BK-8941&contact=baba@example.com'))
        ->assertOk()
        ->assertSee('Box Braids')
        ->assertSee('BK-8941')
        ->assertSee('Babatunde Adeleke');
});

/**
 * The one that matters: a reference is printed on a receipt and read out on
 * WhatsApp, so on its own it must open nobody's appointment.
 */
it('will not show a booking on the reference alone', function () {
    [$seller, $page] = bookableServicePage();

    Booking::factory()->create([
        'store_page_id' => $page->id,
        'reference' => 'BK-8941',
        'customer_name' => 'Vault Token',
        'customer_email' => 'baba@example.com',
        'customer_phone' => '+2348031234567',
    ]);

    get(storeUrl($page->slug, '/book/manage?reference=BK-8941'))
        ->assertOk()
        ->assertDontSee('Vault Token');

    get(storeUrl($page->slug, '/book/manage?reference=BK-8941&contact=someone-else@example.com'))
        ->assertOk()
        ->assertDontSee('Vault Token');
});

it('matches the phone number however the client typed it', function () {
    [$seller, $page] = bookableServicePage();

    Booking::factory()->create([
        'store_page_id' => $page->id,
        'reference' => 'BK-8941',
        'customer_email' => 'baba@example.com',
        'customer_phone' => '+2348031234567',
    ]);

    get(storeUrl($page->slug, '/book/manage?reference=BK-8941&contact=08031234567'))
        ->assertOk()
        ->assertSee('BK-8941');
});

it('answers an empty search with the form rather than a booking', function () {
    [$seller, $page] = bookableServicePage();

    get(storeUrl($page->slug, '/book/manage'))
        ->assertOk()
        ->assertSee('Search Booking')
        ->assertDontSee('Vault Token');
});

/**
 * The confirmation URL is printed in the studio's footer. Somebody who follows
 * it without a booking to hand should land on the lookup, not on a 404 that
 * says the studio has no bookings at all.
 */
it('sends a bare confirmation link to the lookup instead of a 404', function () {
    [$seller, $page] = bookableServicePage();

    get(storeUrl($page->slug, '/book/confirmation'))
        ->assertRedirect(StoreUrl::path('storefront.manage-booking', ['slug' => $page->slug]));
});

it('sends an unknown reference to the lookup too', function () {
    [$seller, $page] = bookableServicePage();

    Booking::factory()->create([
        'store_page_id' => $page->id,
        'reference' => 'BK-0001',
    ]);

    get(storeUrl($page->slug, '/book/confirmation?booking=BK-9999'))
        ->assertRedirect(StoreUrl::path('storefront.manage-booking', ['slug' => $page->slug]));
});

it('still shows the receipt for a reference this studio issued', function () {
    [$seller, $page] = bookableServicePage();

    Booking::factory()->create([
        'store_page_id' => $page->id,
        'reference' => 'BK-8941',
        'customer_email' => 'buyer@example.com',
    ]);

    get(storeUrl($page->slug, '/book/confirmation?booking=BK-8941&contact=buyer@example.com'))
        ->assertOk();
});

it('refuses the receipt on the reference alone', function () {
    [$seller, $page] = bookableServicePage();

    Booking::factory()->create([
        'store_page_id' => $page->id,
        'reference' => 'BK-4242',
        'customer_email' => 'buyer@example.com',
    ]);

    get(storeUrl($page->slug, '/book/confirmation?booking=BK-4242'))
        ->assertRedirect(StoreUrl::path('storefront.manage-booking', ['slug' => $page->slug]));
});
