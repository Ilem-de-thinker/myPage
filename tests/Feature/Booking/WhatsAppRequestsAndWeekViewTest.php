<?php

use App\Domains\Booking\Actions\CreateBooking;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Customers\Models\Customer;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * A published service page open every day, with one service and no staff, so a
 * visitor can book anybody.
 *
 * @return array{0: Seller, 1: Service, 2: StorePage}
 */
function whatsappBookable(): array
{
    $seller = Seller::factory()->create();

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
        'price_kobo' => 1500000,
    ]);

    return [$seller, $service, $page];
}

function nextMondayMorning(): Carbon
{
    return Carbon::now('Africa/Lagos')->addWeeks(2)->next(Carbon::MONDAY)->setTime(11, 0);
}

it('records a booking request and hands the buyer a prefilled message', function () {
    [$seller, $service, $page] = whatsappBookable();
    $startsAt = nextMondayMorning();

    $response = postJson("/{$page->slug}/book/whatsapp", [
        'service' => $service->id,
        'starts_at' => $startsAt->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertOk();

    $booking = Booking::withoutGlobalScopes()->sole();

    expect($response->json('reference'))->toBe($booking->reference)
        ->and($response->json('url'))->toStartWith('https://wa.me/');

    // The message quotes the same reference the seller sees in their inbox.
    $message = rawurldecode(parse_url($response->json('url'), PHP_URL_QUERY));
    expect($message)->toContain($booking->reference)
        ->toContain('Braid consultation');
});

it('does not hold the slot for a WhatsApp request', function () {
    [$seller, $service, $page] = whatsappBookable();
    $startsAt = nextMondayMorning();

    postJson("/{$page->slug}/book/whatsapp", [
        'service' => $service->id,
        'starts_at' => $startsAt->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertOk();

    $booking = Booking::withoutGlobalScopes()->sole();

    // No expiry means it is a request, not a hold, so it never blocks a paying
    // customer from taking the same slot.
    expect($booking->expires_at)->toBeNull();

    $paid = app(CreateBooking::class)
        ->handle($service, $startsAt, null, ['email' => 'payer@example.com']);

    expect($paid->reference)->not->toBe($booking->reference);
});

it('puts a booking request in the seller inbox for them to confirm', function () {
    [$seller, $service, $page] = whatsappBookable();

    postJson("/{$page->slug}/book/whatsapp", [
        'service' => $service->id,
        'starts_at' => nextMondayMorning()->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertOk();

    $booking = Booking::withoutGlobalScopes()->sole();

    // It arrives like any other booking, waiting on the seller.
    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->source)->toBe('whatsapp')
        ->and($booking->history->first()->note)->toBe('Requested over WhatsApp');

    // The list leads with who is coming; the reference is on the detail screen.
    actingAs($seller)->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('Ada Buyer')
        ->assertSee('Awaiting payment');

    actingAs($seller)->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee($booking->reference);
});

it('adds a booking request to the customer book', function () {
    [$seller, $service, $page] = whatsappBookable();

    postJson("/{$page->slug}/book/whatsapp", [
        'service' => $service->id,
        'starts_at' => nextMondayMorning()->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertOk();

    expect(Customer::sole()->name)->toBe('Ada Buyer')
        ->and(Customer::sole()->booking_count)->toBe(1);
});

it('still needs a name and a phone to send a request', function () {
    [$seller, $service, $page] = whatsappBookable();

    postJson("/{$page->slug}/book/whatsapp", [
        'service' => $service->id,
        'starts_at' => nextMondayMorning()->toIso8601String(),
        'phone' => '08031234567',
    ])->assertStatus(422);

    expect(Booking::withoutGlobalScopes()->count())->toBe(0);
});

it('offers the WhatsApp fallback on the booking page', function () {
    [$seller, $service, $page] = whatsappBookable();

    $html = get("/{$page->slug}/book")->assertOk()->getContent();

    expect($html)
        ->toContain('Ask on WhatsApp instead')
        // The endpoint is embedded through @js(), which escapes its slashes.
        ->toContain('book\\/whatsapp');
});

it('shows a week calendar with bookings on the right day', function () {
    [$seller, $service, $page] = whatsappBookable();

    $monday = Carbon::now('Africa/Lagos')->startOfWeek()->addDays(2)->setTime(14, 0);

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_name' => 'Ada Midweek',
        'starts_at' => $monday->copy()->utc(),
        'ends_at' => $monday->copy()->addHour()->utc(),
    ]);

    $html = actingAs($seller)
        ->get(route('bookings.index', ['view' => 'week']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Ada Midweek')
        ->toContain('2:00 PM')
        ->toContain($monday->format('D j M'));
});

it('shows a different week when asked', function () {
    [$seller, $service, $page] = whatsappBookable();

    $nextWeek = Carbon::now('Africa/Lagos')->startOfWeek()->addWeek()->addDays(2)->setTime(15, 0);

    Booking::factory()->confirmed()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_name' => 'Ada Next Week',
        'starts_at' => $nextWeek->copy()->utc(),
        'ends_at' => $nextWeek->copy()->addHour()->utc(),
    ]);

    // This week has nothing; the week that has it does.
    actingAs($seller)->get(route('bookings.index', ['view' => 'week']))
        ->assertOk()
        ->assertDontSee('Ada Next Week');

    actingAs($seller)->get(route('bookings.index', [
        'view' => 'week',
        'week' => $nextWeek->copy()->startOfWeek()->toDateString(),
    ]))
        ->assertOk()
        ->assertSee('Ada Next Week');
});

it('never shows one seller another seller\'s week', function () {
    [$seller, $service, $page] = whatsappBookable();
    [$otherSeller, $otherService, $otherPage] = whatsappBookable();

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
        'service_id' => $otherService->id,
        'customer_name' => 'Secret Guest',
    ]);

    actingAs($seller)->get(route('bookings.index', ['view' => 'week']))
        ->assertOk()
        ->assertDontSee('Secret Guest');
});
