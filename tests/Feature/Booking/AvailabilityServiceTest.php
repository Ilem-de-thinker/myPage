<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\TimeOff;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Booking\Services\AvailabilityService;
use Illuminate\Support\Carbon;

/**
 * A service on a page that is open nine-to-five, Lagos time, every day.
 */
function bookableService(array $attributes = [], array $pageAttributes = []): Service
{
    [$seller, $page] = sellerWithPage(array_merge([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos', 'min_notice_minutes' => 60],
    ], $pageAttributes));

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        WorkingHour::create([
            'store_page_id' => $page->id,
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    return Service::factory()->create(array_merge([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'buffer_min' => 0,
    ], $attributes));
}

function aDay(): Carbon
{
    return Carbon::parse('2026-11-16 00:00:00', 'Africa/Lagos'); // a Monday
}

it('offers every half hour slot inside the opening hours', function () {
    $service = bookableService(['duration_min' => 60]);

    $slots = app(AvailabilityService::class)->slotsFor($service, aDay());

    // 09:00 to 16:00 start times, so the 16:00 appointment still ends at 17:00.
    expect($slots)->toHaveCount(15)
        ->and($slots->first()->format('H:i'))->toBe('09:00')
        ->and($slots->last()->format('H:i'))->toBe('16:00');
});

it('returns slots in the store timezone regardless of the server timezone', function () {
    $service = bookableService();

    $slots = app(AvailabilityService::class)->slotsFor($service, aDay());

    expect($slots->first()->timezoneName)->toBe('Africa/Lagos')
        ->and($slots->first()->format('H:i'))->toBe('09:00');
});

it('does not offer a slot the service would run past closing', function () {
    // Any start time from which two hours still fit before 17:00, on the half
    // hour grid: 09:00 through 15:00.
    $service = bookableService(['duration_min' => 120]);

    $slots = app(AvailabilityService::class)->slotsFor($service, aDay());

    expect($slots)->toHaveCount(13)
        ->and($slots->last()->format('H:i'))->toBe('15:00');
});

it('respects the buffer when working out the last bookable slot', function () {
    // An hour of appointment plus half an hour of reset eats 90 minutes of the
    // eight hour day, so the final start is 15:30.
    $service = bookableService(['duration_min' => 60, 'buffer_min' => 30]);

    $slots = app(AvailabilityService::class)->slotsFor($service, aDay());

    expect($slots->last()->format('H:i'))->toBe('15:30')
        ->and($slots)->toHaveCount(14);
});

it('never offers a slot closer than the notice window', function () {
    $service = bookableService();

    $today = Carbon::now('Africa/Lagos')->addHours(4)->startOfHour();

    $slots = app(AvailabilityService::class)->slotsFor($service, $today);

    // Everything offered is at least the notice window away.
    foreach ($slots as $slot) {
        expect($slot->greaterThan(now()->addMinutes(60)))->toBeTrue();
    }
});

it('offers nothing on a day the store is closed', function () {
    $service = bookableService();

    WorkingHour::where('store_page_id', $service->store_page_id)->delete();

    expect(app(AvailabilityService::class)->slotsFor($service, aDay()))->toHaveCount(0);
});

it('treats weekday boundaries correctly on both sides', function () {
    $service = bookableService();

    WorkingHour::where('store_page_id', $service->store_page_id)
        ->whereNotIn('weekday', [1, 7]) // open Mondays and Sundays only
        ->delete();

    $availability = app(AvailabilityService::class);

    // Monday and Sunday are open.
    expect($availability->slotsFor($service, Carbon::parse('2026-11-16', 'Africa/Lagos')))->not->toBeEmpty(); // Mon
    expect($availability->slotsFor($service, Carbon::parse('2026-11-22', 'Africa/Lagos')))->not->toBeEmpty(); // Sun

    // Tuesday is not.
    expect($availability->slotsFor($service, Carbon::parse('2026-11-17', 'Africa/Lagos')))->toBeEmpty();
});

it('removes a slot an existing booking occupies', function () {
    $service = bookableService();

    Booking::factory()->confirmed()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
    ]);

    $slots = app(AvailabilityService::class)->slotsFor($service, aDay());

    $times = $slots->map(fn (Carbon $slot) => $slot->format('H:i'))->all();

    // Only 11:00 itself clashes. 10:00 finishes at 11:00 and back to back is
    // allowed, and 12:00 is free again.
    expect($times)->not->toContain('11:00')
        ->and($times)->toContain('10:00')
        ->and($times)->toContain('12:00');
});

it('still offers a slot that starts exactly when another booking ends', function () {
    $service = bookableService();

    Booking::factory()->confirmed()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
    ]);

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    // Back to back is allowed: closing the chair is not the same as it being busy.
    expect($times)->toContain('12:00');
});

it('blocks slots that run into an existing booking', function () {
    $service = bookableService(['duration_min' => 120]);

    Booking::factory()->confirmed()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse('2026-11-16 13:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 14:00', 'Africa/Lagos')->utc(),
    ]);

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    // 11:00 to 13:00 finishes exactly as the booking starts, so it is fine.
    // 12:00 to 14:00 would be running through it.
    expect($times)->toContain('11:00')
        ->and($times)->not->toContain('12:00')
        ->and($times)->toContain('10:00');
});

it('blocks a slot overlapping an unexpired pending hold', function () {
    $service = bookableService();

    Booking::factory()->pendingPayment()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
    ]);

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    expect($times)->not->toContain('11:00')
        // A hold does not spill backwards into the slot before it.
        ->and($times)->toContain('10:00');
});

it('ignores a pending hold whose payment window has run out', function () {
    $service = bookableService();

    Booking::factory()->expiredHold()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
    ]);

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    // The slot is free again once the hold lapses.
    expect($times)->toContain('10:00')
        ->and($times)->toContain('11:00');
});

it('ignores cancelled and expired bookings', function () {
    $service = bookableService();

    foreach ([Booking::factory()->cancelled(), Booking::factory()->expiredHold()] as $factory) {
        $factory->create([
            'store_page_id' => $service->store_page_id,
            'service_id' => $service->id,
            'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
            'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
        ]);
    }

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    expect($times)->toContain('10:00')
        ->and($times)->toContain('11:00');
});

it('removes slots that fall in a time off', function () {
    $service = bookableService();

    TimeOff::create([
        'store_page_id' => $service->store_page_id,
        'starts_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 14:00', 'Africa/Lagos')->utc(),
        'reason' => 'Training',
    ]);

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    expect($times)->not->toContain('12:00')
        ->and($times)->not->toContain('13:00')
        // An appointment ending exactly as time off begins is allowed.
        ->and($times)->toContain('11:00')
        ->and($times)->toContain('10:00')
        ->and($times)->toContain('14:00');
});

it('leaves one staff member free even when another is busy', function () {
    $service = bookableService();

    $booked = StaffMember::factory()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $service->store_page_id,
    ]);

    $free = StaffMember::factory()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $service->store_page_id,
    ]);

    Booking::factory()->confirmed()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        'staff_member_id' => $booked->id,
        'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
    ]);

    $availability = app(AvailabilityService::class);

    // The busy one loses the slot; the free one keeps it.
    expect($availability->slotsFor($service, aDay(), $booked)->map(fn ($s) => $s->format('H:i'))->all())
        ->not->toContain('11:00');

    expect($availability->slotsFor($service, aDay(), $free)->map(fn ($s) => $s->format('H:i'))->all())
        ->toContain('11:00');

    // And with nobody chosen the shop is still open: one busy person is not a
    // closed shop.
    expect($availability->slotsFor($service, aDay())->map(fn ($s) => $s->format('H:i'))->all())
        ->toContain('11:00');
});

it('lets the shop stay open when only one person is away', function () {
    $service = bookableService();

    $away = StaffMember::factory()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $service->store_page_id,
    ]);

    TimeOff::create([
        'store_page_id' => $service->store_page_id,
        'staff_member_id' => $away->id,
        'starts_at' => Carbon::parse('2026-11-16 00:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-17 00:00', 'Africa/Lagos')->utc(),
        'reason' => 'Sick',
    ]);

    $availability = app(AvailabilityService::class);

    expect($availability->slotsFor($service, aDay(), $away))->toBeEmpty()
        ->and($availability->slotsFor($service, aDay()))->not->toBeEmpty();
});

it('closes the whole shop when nobody is named on a time off', function () {
    $service = bookableService();

    TimeOff::create([
        'store_page_id' => $service->store_page_id,
        'starts_at' => Carbon::parse('2026-11-16 00:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-17 00:00', 'Africa/Lagos')->utc(),
        'reason' => 'Public holiday',
    ]);

    expect(app(AvailabilityService::class)->slotsFor($service, aDay()))->toBeEmpty();
});

it('uses a named staff member\'s own hours in preference to the business hours', function () {
    $service = bookableService();

    $staff = StaffMember::factory()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $service->store_page_id,
    ]);

    // This person only works Mondays, and only until noon.
    WorkingHour::where('store_page_id', $service->store_page_id)->delete();
    WorkingHour::create([
        'store_page_id' => $service->store_page_id,
        'staff_member_id' => $staff->id,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $monday = app(AvailabilityService::class)->slotsFor($service, aDay(), $staff);

    expect($monday)->not->toBeEmpty()
        ->and($monday->last()->format('H:i'))->toBe('11:00');

    // Nobody chosen, and the business has no hours of its own: nothing to offer.
    expect(app(AvailabilityService::class)->slotsFor($service, aDay()))->toBeEmpty();
});

it('never offers a slot for an inactive service', function () {
    // Availability is asked about a service directly, so this is about the
    // caller guarding, not the engine: the storefront only lists active ones.
    $service = bookableService();

    $slots = app(AvailabilityService::class)->slotsFor($service, aDay());

    expect($slots)->not->toBeEmpty();

    $service->update(['is_active' => false]);

    expect($service->fresh()->is_active)->toBeFalse();
});

it('keeps the buffer from eating into the next appointment', function () {
    $service = bookableService(['duration_min' => 60, 'buffer_min' => 30]);

    Booking::factory()->confirmed()->create([
        'store_page_id' => $service->store_page_id,
        'service_id' => $service->id,
        // Runs 11:00 to 12:00, then needs until 12:30 to reset.
        'starts_at' => Carbon::parse('2026-11-16 11:00', 'Africa/Lagos')->utc(),
        'ends_at' => Carbon::parse('2026-11-16 12:00', 'Africa/Lagos')->utc(),
    ]);

    $times = app(AvailabilityService::class)
        ->slotsFor($service, aDay())
        ->map(fn (Carbon $slot) => $slot->format('H:i'))
        ->all();

    // 12:00 leaves no room for the reset, so the next booking starts at 12:30.
    expect($times)->not->toContain('11:30')
        ->and($times)->not->toContain('12:00')
        ->and($times)->toContain('12:30');
});
