<?php

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Catalog\Models\Product;

use function Pest\Laravel\actingAs;

/**
 * A service seller whose page is open every day from nine to five.
 */
function serviceSeller(array $pageAttributes = []): array
{
    [$seller, $page] = sellerWithPage(array_merge([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos'],
    ], $pageAttributes));

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        WorkingHour::create([
            'store_page_id' => $page->id,
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    return [$seller, $page];
}

function bookableServiceFor(array $pageAttributes = [], array $attributes = []): Service
{
    [$seller, $page] = serviceSeller($pageAttributes);

    return Service::factory()->create(array_merge([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'price_kobo' => 1500000,
    ], $attributes));
}

it('lets a seller add a service and prices it in kobo', function () {
    [$seller] = serviceSeller();

    actingAs($seller)->post(route('services.store'), [
        'name' => 'Braid consultation',
        'description' => 'What we will do together.',
        'duration_min' => 90,
        'buffer_min' => 15,
        'price' => '12500.50',
        'deposit_type' => 'percent',
        'deposit_value' => 30,
        'is_active' => '1',
    ])->assertRedirect(route('services.index'));

    $service = Service::withoutGlobalScopes()->sole();

    expect($service->name)->toBe('Braid consultation')
        // 12500.50 naira is 1250050 kobo, never a float.
        ->and($service->price_kobo)->toBe(1250050)
        ->and($service->duration_min)->toBe(90)
        ->and($service->buffer_min)->toBe(15)
        ->and($service->deposit_type)->toBe(DepositType::Percent)
        ->and($service->deposit_value)->toBe(30)
        ->and($service->amountDueKobo())->toBe(375015);
});

it('stores a fixed deposit in kobo, not naira', function () {
    [$seller] = serviceSeller();

    actingAs($seller)->post(route('services.store'), [
        'name' => 'Haircut',
        'duration_min' => 45,
        'price' => '5000',
        'deposit_type' => 'fixed',
        'deposit_value' => '2000',
        'is_active' => '1',
    ]);

    expect(Service::withoutGlobalScopes()->sole()->deposit_value)->toBe(200000);
});

it('lists services and warns when the seller has no opening hours', function () {
    [$seller, $page] = serviceSeller();
    Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'name' => 'Haircut']);

    actingAs($seller)->get(route('services.index'))
        ->assertOk()
        ->assertSee('Haircut')
        ->assertDontSee('no opening hours set');

    // A seller who never set hours can sell nothing bookable.
    WorkingHour::where('store_page_id', $page->id)->delete();

    actingAs($seller)->get(route('services.index'))
        ->assertOk()
        ->assertSee('no opening hours set');
});

it('never shows a store seller the services screens', function () {
    [$seller] = sellerWithPage(['theme' => 'store']);

    actingAs($seller)->get(route('services.index'))->assertNotFound();
    actingAs($seller)->post(route('services.store'), ['name' => 'X', 'duration_min' => 30])->assertNotFound();
});

it('never shows one seller another seller\'s service', function () {
    [$seller] = serviceSeller();
    [$otherSeller, $otherPage] = serviceSeller();

    $foreign = Service::factory()->create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
        'name' => 'Secret Treatment',
    ]);

    actingAs($seller)->get(route('services.index'))->assertOk()->assertDontSee('Secret Treatment');
    actingAs($seller)->get(route('services.edit', $foreign))->assertNotFound();

    actingAs($seller)->delete(route('services.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh()->is_active)->toBeTrue();
});

it('hides a service rather than deleting it, so bookings survive', function () {
    [$seller, $page] = serviceSeller();

    $service = Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
    ]);

    actingAs($seller)->delete(route('services.destroy', $service))->assertRedirect();

    expect($service->fresh()->is_active)->toBeFalse()
        ->and($booking->fresh())->not->toBeNull();
});

it('edits a service and regenerates its slug from the new name', function () {
    [$seller, $page] = serviceSeller();

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'name' => 'Old Name',
    ]);

    $oldSlug = $service->slug;

    actingAs($seller)->put(route('services.update', $service), [
        'name' => 'New Name',
        'duration_min' => 30,
        'price' => '1000',
        'deposit_type' => 'none',
    ])->assertRedirect(route('services.index'));

    $service->refresh();

    expect($service->name)->toBe('New Name')
        ->and($service->slug)->not->toBe($oldSlug);
});

it('rejects a service with an impossible duration', function () {
    [$seller] = serviceSeller();

    actingAs($seller)->post(route('services.store'), [
        'name' => 'Impossible',
        'duration_min' => 0,
        'deposit_type' => 'none',
    ])->assertSessionHasErrors('duration_min');
});

it('shows the bookings inbox split into today, upcoming and past', function () {
    [$seller, $page] = serviceSeller();
    $service = Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    $today = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_name' => 'Ada Today',
        'starts_at' => now()->addHours(3),
    ]);

    $upcoming = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_name' => 'Bode Upcoming',
        'starts_at' => now()->addDays(5),
    ]);

    $past = Booking::factory()->completed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_name' => 'Chidi Past',
        'starts_at' => now()->subDays(3),
    ]);

    $index = route('bookings.index');

    actingAs($seller)->get($index.'?filter=upcoming')
        ->assertOk()
        ->assertSee('Bode Upcoming')
        ->assertDontSee('Chidi Past');

    actingAs($seller)->get($index.'?filter=past')
        ->assertOk()
        ->assertSee('Chidi Past')
        ->assertDontSee('Bode Upcoming');
});

it('moves a booking through its lifecycle and writes history', function () {
    [$seller, $page] = serviceSeller();
    $service = Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    $booking = Booking::factory()->pendingPayment()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
    ]);

    actingAs($seller)->post(route('bookings.status', $booking), ['status' => BookingStatus::Confirmed->value]);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->history)->toHaveCount(1);

    // Confirmed the hold no longer needs an expiry.
    expect($booking->fresh()->expires_at)->toBeNull();
});

it('refuses a booking status move the lifecycle does not allow', function () {
    [$seller, $page] = serviceSeller();
    $service = Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    $booking = Booking::factory()->completed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
    ]);

    // A finished appointment cannot jump back to pending payment.
    actingAs($seller)->post(route('bookings.status', $booking), ['status' => BookingStatus::PendingPayment->value]);

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

it('never shows or edits another seller\'s booking', function () {
    [$seller] = serviceSeller();
    [$otherSeller, $otherPage] = serviceSeller();
    $otherService = Service::factory()->create(['seller_id' => $otherSeller->id, 'store_page_id' => $otherPage->id]);

    $foreign = Booking::factory()->confirmed()->create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
        'service_id' => $otherService->id,
        'customer_name' => 'Secret Guest',
    ]);

    actingAs($seller)->get(route('bookings.index'))->assertOk()->assertDontSee('Secret Guest');
    actingAs($seller)->get(route('bookings.show', $foreign))->assertNotFound();

    actingAs($seller)->post(route('bookings.status', $foreign), ['status' => BookingStatus::Completed->value])
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('shows the booking detail with its details and next steps', function () {
    [$seller, $page] = serviceSeller();
    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'name' => 'Braid consultation',
        'price_kobo' => 1250000,
    ]);

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_name' => 'Ada Buyer',
        'customer_phone' => '08031234567',
        // The booking records what was agreed at the time, not today's price.
        'price_kobo' => 1250000,
    ]);

    actingAs($seller)->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('Ada Buyer')
        ->assertSee('Braid consultation')
        ->assertSee('₦12,500')
        ->assertSee('Completed')
        ->assertSee('Cancelled');
});

it('never lets a store seller reach the bookings inbox', function () {
    [$seller] = sellerWithPage(['theme' => 'store']);

    actingAs($seller)->get(route('bookings.index'))->assertNotFound();
});

it('never exposes a service on a store seller\'s page through the shared models', function () {
    // A store page can carry rows in the booking tables, but the seller-facing
    // screens refuse the theme outright, so the data can never be edited there.
    [$seller, $page] = sellerWithPage(['theme' => 'store']);

    Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    actingAs($seller)->get(route('services.index'))->assertNotFound();

    expect(Product::withoutGlobalScopes()->count())->toBe(0);
});

it('counts the service dashboard in bookings, not orders', function () {
    [$seller, $page] = serviceSeller();
    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    // Kept and paid for this week, so the seller has earned the deposit.
    Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'price_kobo' => 1500000,
        'deposit_kobo' => 300000,
        'created_at' => now()->startOfWeek()->addHours(2),
    ]);

    // A hold the buyer has not paid for yet: an appointment, not money.
    Booking::factory()->pendingPayment()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'price_kobo' => 1500000,
        'deposit_kobo' => 700000,
        'created_at' => now()->startOfWeek()->addHours(3),
    ]);

    // Last week, so it belongs in the comparison and nowhere else. Its slot is
    // still ahead, so it counts as work in hand.
    Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'price_kobo' => 1500000,
        'deposit_kobo' => 900000,
        'created_at' => now()->startOfWeek()->subWeek()->addDay(),
    ]);

    // Cancelled, but the slot is in the future, so it is not work still ahead.
    Booking::factory()->cancelled()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'price_kobo' => 1500000,
        'deposit_kobo' => 500000,
        'created_at' => now()->subWeeks(3),
        'starts_at' => now()->addDay(),
    ]);

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Bookings This Week')
        // Two requests this week against one last week.
        ->assertSee('+1 vs last week')
        // Only the kept deposit counts, and never the full price behind it.
        ->assertSee('₦3,000')
        ->assertDontSee('₦15,000')
        // Three appointments are still ahead: ₦3,000 + ₦7,000 + ₦9,000.
        ->assertSee('Upcoming Bookings')
        ->assertSee('₦19,000')
        // The cancelled booking would make it ₦24,000 if it leaked in.
        ->assertDontSee('₦24,000')
        ->assertDontSee('Abandoned Carts');
});
