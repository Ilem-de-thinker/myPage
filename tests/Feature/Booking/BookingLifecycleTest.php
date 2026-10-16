<?php

use App\Domains\Booking\Actions\CreateBooking;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Jobs\SendBookingReminders;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Booking\Notifications\BookingReminder;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

/**
 * A confirmed booking tomorrow at a fixed hour, with the shop open all week.
 *
 * @return array{0: Seller, 1: Booking, 2: Service}
 */
function tomorrowBooking(array $bookingAttributes = [], array $serviceAttributes = []): array
{
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos', 'min_notice_minutes' => 60],
    ]);

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        WorkingHour::create([
            'store_page_id' => $page->id,
            'weekday' => $weekday,
            'start_time' => '00:00',
            'end_time' => '23:59',
        ]);
    }

    $service = Service::factory()->create(array_merge([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'price_kobo' => 1500000,
    ], $serviceAttributes));

    // Exactly 24 hours out. Rounding to the hour here would drift the booking
    // out of the reminder job's 30 minute window.
    $startsAt = Carbon::now('Africa/Lagos')->addDay();

    $booking = Booking::factory()->confirmed()->create(array_merge([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'starts_at' => $startsAt->utc(),
        'ends_at' => $startsAt->copy()->addHour()->utc(),
        'customer_email' => 'buyer@example.com',
    ], $bookingAttributes));

    return [$seller, $booking, $service];
}

it('reschedules a booking onto a free slot and frees the old one', function () {
    [$seller, $booking, $service] = tomorrowBooking();

    $newStart = Carbon::now('Africa/Lagos')->addDays(3)->setTime(10, 0);

    actingAs($seller)->post(route('bookings.reschedule.store', $booking), [
        'starts_at' => $newStart->toIso8601String(),
    ])->assertRedirect(route('bookings.show', $booking));

    $moved = $booking->fresh();

    expect($moved->starts_at->timezone('Africa/Lagos')->format('D j M, g:i A'))
        ->toBe($newStart->format('D j M, g:i A'))
        ->and($moved->ends_at->equalTo($moved->starts_at->copy()->addHour()))->toBeTrue();

    // The move is recorded, so the seller can see when it happened.
    expect($moved->history->first()->note)->toContain('Moved to');
});

it('refuses to move a booking into an occupied slot', function () {
    [$seller, $booking] = tomorrowBooking();

    Booking::factory()->confirmed()->create([
        'store_page_id' => $booking->store_page_id,
        'service_id' => $booking->service_id,
        'starts_at' => Carbon::now('Africa/Lagos')->addDays(3)->setTime(10, 0)->utc(),
        'ends_at' => Carbon::now('Africa/Lagos')->addDays(3)->setTime(11, 0)->utc(),
    ]);

    $original = $booking->fresh()->starts_at;

    actingAs($seller)->post(route('bookings.reschedule.store', $booking), [
        'starts_at' => Carbon::now('Africa/Lagos')->addDays(3)->setTime(10, 0)->toIso8601String(),
    ])->assertSessionHasErrors('starts_at');

    expect($booking->fresh()->starts_at->equalTo($original))->toBeTrue();
});

it('locks a booking inside the cancellation window', function () {
    // Starting in two hours with a 24 hour window.
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos', 'cancellation_window_hours' => 24],
    ]);

    $soon = Carbon::now('Africa/Lagos')->addHours(2);

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'starts_at' => $soon->utc(),
        'ends_at' => $soon->copy()->addHour()->utc(),
    ]);

    actingAs($seller)->post(route('bookings.reschedule.store', $booking), [
        'starts_at' => Carbon::now('Africa/Lagos')->addDays(4)->setTime(10, 0)->toIso8601String(),
    ])->assertSessionHasErrors('starts_at');

    expect($booking->fresh()->starts_at->equalTo($soon->copy()->utc()->startOfSecond()))->toBeTrue();
});

it('tells the seller why a booking cannot be moved online', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos', 'cancellation_window_hours' => 48],
    ]);

    $soon = Carbon::now('Africa/Lagos')->addHours(6);

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_phone' => '08031234567',
        'starts_at' => $soon->utc(),
        'ends_at' => $soon->copy()->addHour()->utc(),
    ]);

    actingAs($seller)->get(route('bookings.reschedule.show', $booking))
        ->assertOk()
        ->assertSee('Too late to move this online')
        ->assertSee('08031234567');
});

it('cancels a booking and frees the slot', function () {
    [$seller, $booking] = tomorrowBooking();

    // The reminder fixtures sit exactly 24h out, which is not on the half-hour
    // booking grid, so this test uses a slot a customer could actually take.
    $slot = Carbon::now('Africa/Lagos')->addDays(3)->setTime(10, 0);

    $booking->update([
        'starts_at' => $slot->copy()->utc(),
        'ends_at' => $slot->copy()->addHour()->utc(),
    ]);

    actingAs($seller)->post(route('bookings.cancel', $booking))->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);

    // The strongest proof the slot was freed: somebody else can now book it.
    $replacement = app(CreateBooking::class)
        ->handle($booking->service, $slot, null, ['name' => 'Someone Else', 'phone' => '08030000000']);

    expect($replacement->starts_at->equalTo($booking->fresh()->starts_at))->toBeTrue();
});

it('never cancels or moves another seller\'s booking', function () {
    [, $booking] = tomorrowBooking();

    [$stranger] = sellerWithPage(['theme' => 'service', 'status' => 'published']);

    actingAs($stranger)->post(route('bookings.cancel', $booking))->assertNotFound();
    actingAs($stranger)->get(route('bookings.reschedule.show', $booking))->assertNotFound();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('sends the 24 hour reminder exactly once', function () {
    Notification::fake();

    [, $booking] = tomorrowBooking();

    $job = new SendBookingReminders(hoursBefore: 24);

    $job->handle();
    $job->handle();

    Notification::assertSentTimes(BookingReminder::class, 1);

    expect($booking->fresh()->reminded_24h_at)->not->toBeNull()
        ->and($booking->fresh()->reminded_2h_at)->toBeNull();
});

it('tracks the two lead times separately', function () {
    Notification::fake();

    [, $booking] = tomorrowBooking();

    (new SendBookingReminders(hoursBefore: 24))->handle();

    // The 24h reminder must not satisfy the 2h one when it comes round.
    Notification::fake();
    (new SendBookingReminders(hoursBefore: 2))->handle();

    Notification::assertNothingSent();
});

it('reminds a booking that is two hours away', function () {
    Notification::fake();

    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos'],
    ]);

    $soon = Carbon::now('Africa/Lagos')->addHours(2);

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'customer_email' => 'soon@example.com',
        'starts_at' => $soon->utc(),
        'ends_at' => $soon->copy()->addHour()->utc(),
    ]);

    (new SendBookingReminders(hoursBefore: 2))->handle();

    Notification::assertSentTimes(BookingReminder::class, 1);
});

it('never reminds for a cancelled booking or one with no email', function () {
    Notification::fake();

    tomorrowBooking(['status' => BookingStatus::Cancelled]);
    tomorrowBooking(['customer_email' => null]);

    (new SendBookingReminders(hoursBefore: 24))->handle();

    Notification::assertNothingSent();
});

it('writes a reminder the buyer can act on', function () {
    [, $booking] = tomorrowBooking([
        'customer_name' => 'Ada Buyer',
        'customer_phone' => '08031234567',
        'notes' => 'Coming with my daughter.',
    ]);

    $reminder = new BookingReminder($booking->fresh()->load(['service', 'staffMember', 'storePage']), 24);

    $mail = $reminder->toMail(new stdClass);

    $lines = implode(' ', $mail->introLines);

    expect($mail->subject)->toContain('Tomorrow')
        ->and($mail->greeting)->toContain('Ada Buyer')
        ->and($lines)->toContain('Coming with my daughter.')
        ->and($lines)->toContain($booking->reference);

    // And a wa.me link the seller can forward by hand.
    expect($reminder->whatsappLink())->toStartWith('https://wa.me/2348031234567?text=');
});
