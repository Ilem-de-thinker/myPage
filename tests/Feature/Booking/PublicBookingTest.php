<?php

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;
use Illuminate\Support\Carbon;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * A published service page, open every day nine to five, with one service.
 *
 * @return array{0: Service, 1: StorePage}
 */
function bookablePage(string $date): array
{
    $seller = Seller::factory()->create();

    // withContent() mirrors what onboarding really builds, otherwise the
    // booking page has no widget to render.
    $page = StorePage::factory()
        ->for($seller)
        ->withContent()
        ->create([
            'theme' => 'service',
            'status' => 'published',
            'settings' => ['timezone' => 'Africa/Lagos', 'min_notice_minutes' => 60],
        ]);

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        WorkingHour::create([
            'store_page_id' => $page->id,
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'name' => 'Braid consultation',
        'duration_min' => 60,
        'price_kobo' => 1500000,
    ]);

    // Deposits are paid into the seller's own gateway account.
    SellerGateway::factory()->for($seller)->create();

    return [$service, $page];
}

/**
 * A date the page is open, far enough ahead to clear the notice window.
 */
function openDate(): string
{
    return Carbon::now('Africa/Lagos')->addWeeks(2)->next(Carbon::MONDAY)->toDateString();
}

it('serves the slot list for a day', function () {
    [$service, $page] = bookablePage('x');

    $response = get(storeUrl($page->slug, "/availability?service={$service->id}&date=").openDate())
        ->assertOk()
        ->assertJsonStructure(['slots' => [['starts_at', 'label']]]);

    $slots = $response->json('slots');

    expect($slots)->not->toBeEmpty()
        ->and($slots[0]['label'])->toBe('9:00 AM')
        // The offset travels with the time so the browser cannot misread it.
        ->and($slots[0]['starts_at'])->toContain('+01:00');
});

it('drops a taken slot from the list', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    Booking::factory()->confirmed()->create([
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse($date.' 12:00', 'Africa/Lagos')->utc(),
    ]);

    $labels = collect(get(storeUrl($page->slug, "/availability?service={$service->id}&date={$date}"))->json('slots'))
        ->pluck('label')
        ->all();

    expect($labels)->not->toContain('11:00 AM')
        ->and($labels)->toContain('10:00 AM');
});

it('refuses to list another store\'s service', function () {
    [$service, $page] = bookablePage('x');

    $foreign = Service::factory()->create([
        'store_page_id' => StorePage::factory()->create([
            'theme' => 'service',
            'status' => 'published',
        ])->id,
    ]);

    get(storeUrl($page->slug, "/availability?service={$foreign->id}&date=").openDate())->assertNotFound();
});

it('books a genuinely free slot and shows the confirmation', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    // Nothing owed means no payment step at all.
    $service->update(['price_kobo' => 0]);

    post(storeUrl($page->slug, '/book'), [
        'service' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertRedirect();

    $booking = Booking::withoutGlobalScopes()->sole();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->customer_name)->toBe('Ada Buyer');

    get(storeUrl($page->slug, "/book/confirmation?booking={$booking->reference}"))
        ->assertOk()
        ->assertSee('Braid consultation')
        ->assertSee($booking->reference);
});

it('sends a booking with no deposit but a real price to payment', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    // No deposit means the buyer pays the whole amount up front.
    $service->update(['price_kobo' => 1500000, 'deposit_type' => DepositType::None, 'deposit_value' => 0]);

    config([
        'services.paystack.secret' => 'sk_test',
        'mypage.gateway' => 'paystack',
    ]);
    fakePaystack();

    post(storeUrl($page->slug, '/book'), [
        'service' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
        'email' => 'ada@example.com',
    ])->assertRedirect('https://checkout.paystack.com/test-checkout');

    $booking = Booking::withoutGlobalScopes()->sole();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        // The whole price is owed, not a deposit.
        ->and($booking->deposit_kobo)->toBe(0)
        ->and($booking->amountDueKobo())->toBe(1500000);
});

it('sends a deposit booking to payment instead of confirming it', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    $service->update([
        'price_kobo' => 1500000,
        'deposit_type' => DepositType::Percent,
        'deposit_value' => 30,
    ]);

    config([
        'services.paystack.secret' => 'sk_test',
        'mypage.gateway' => 'paystack',
    ]);
    fakePaystack();

    post(storeUrl($page->slug, '/book'), [
        'service' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
        'email' => 'ada@example.com',
    ])->assertRedirect('https://checkout.paystack.com/test-checkout');

    $booking = Booking::withoutGlobalScopes()->sole();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->deposit_kobo)->toBe(450000);
});

it('refuses a slot that was taken while the form was open', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    Booking::factory()->confirmed()->create([
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse($date.' 12:00', 'Africa/Lagos')->utc(),
    ]);

    post(storeUrl($page->slug, '/book'), [
        'service' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertSessionHasErrors('starts_at');

    expect(Booking::withoutGlobalScopes()->count())->toBe(1);
});

it('never serves the booking pages to a store seller', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    get(storeUrl($page->slug, '/book'))->assertNotFound();
    get(storeUrl($page->slug, '/availability?service=1&date=').openDate())->assertNotFound();
});

it('shows an honest empty state when the seller has no services', function () {
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->withContent()->create([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos'],
    ]);

    // No services at all: the widget must not pretend a form will work.
    get(storeUrl($page->slug, '/book'))
        ->assertOk()
        ->assertSee('Bookings open soon');
});

it('renders the booking widget with the real service list', function () {
    [$service, $page] = bookablePage('x');

    $html = get(storeUrl($page->slug, '/book'))->assertOk()->getContent();

    expect($html)
        ->toContain('Braid consultation')
        // The form posts here; the widget posts nowhere else.
        ->toContain(StoreUrl::path('storefront.booking.store', ['slug' => $page->slug]))
        ->toContain('Book this time')
        // The slot list is fetched, so the URL appears inside the Alpine setup
        // rather than as plain markup.
        ->toContain('/availability');
});
