<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\TimeOff;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Booking\Services\AvailabilityService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

/**
 * The whole point of these tests: a seller configures availability through the
 * dashboard and a stranger can then actually book. Anything below that writes
 * working_hours directly would miss the gap this suite exists to catch.
 */
/**
 * A grid with all seven days open, 09:00 to 17:00.
 *
 * @return array<int, array{enabled: int, start: string, end: string}>
 */
function openWeek(): array
{
    $week = [];

    foreach (range(1, 7) as $weekday) {
        $week[$weekday] = ['enabled' => 1, 'start' => '09:00', 'end' => '17:00'];
    }

    return $week;
}

function availabilitySeller(array $pageAttributes = []): array
{
    return sellerWithPage(array_merge([
        'theme' => 'service',
        'status' => 'published',
        'settings' => ['timezone' => 'Africa/Lagos', 'min_notice_minutes' => 60],
    ], $pageAttributes));
}

it('lets a seller open a week from the dashboard and unblocks bookings', function () {
    [$seller, $page] = availabilitySeller();

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'price_kobo' => 0,
    ]);

    // Before any hours are set, nothing is bookable at all.
    expect(app(AvailabilityService::class)->slotsFor($service, Carbon::parse('2026-11-16', 'Africa/Lagos')))->toBeEmpty();

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => [
            1 => ['enabled' => 1, 'start' => '09:00', 'end' => '17:00'],
            2 => ['enabled' => 1, 'start' => '09:00', 'end' => '17:00'],
            3 => ['enabled' => 0],
            4 => ['enabled' => 1, 'start' => '10:00', 'end' => '15:00'],
            5 => ['enabled' => 1, 'start' => '09:00', 'end' => '17:00'],
            6 => ['enabled' => 1, 'start' => '09:00', 'end' => '13:00'],
            7 => ['enabled' => 0],
        ],
    ])->assertRedirect();

    // Five open days, and Wednesday stayed shut.
    expect(WorkingHour::where('store_page_id', $page->id)->distinct()->count('weekday'))->toBe(5);

    // And a visitor can now see them.
    $monday = app(AvailabilityService::class)->slotsFor($service, Carbon::parse('2026-11-16', 'Africa/Lagos'));
    $wednesday = app(AvailabilityService::class)->slotsFor($service, Carbon::parse('2026-11-18', 'Africa/Lagos'));

    expect($monday)->not->toBeEmpty()
        ->and($monday->first()->format('H:i'))->toBe('09:00')
        ->and($wednesday)->toBeEmpty();
});

it('replaces the previous schedule rather than stacking on it', function () {
    [$seller, $page] = availabilitySeller();

    $grid = ['days' => openWeek()];

    actingAs($seller)->put(route('availability.update', ['staff' => null]), $grid)->assertRedirect();
    actingAs($seller)->put(route('availability.update', ['staff' => null]), $grid)->assertRedirect();

    // Saving twice must not produce two windows for every day.
    expect(WorkingHour::where('store_page_id', $page->id)->count())->toBe(7);

    // And closing one day removes exactly that day.
    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => [1 => ['enabled' => 1, 'start' => '09:00', 'end' => '17:00'], 2 => ['enabled' => 0]],
    ])->assertRedirect();

    expect(WorkingHour::where('store_page_id', $page->id)->distinct()->count('weekday'))->toBe(1);
});

it('refuses an open day with no usable times', function () {
    [$seller] = availabilitySeller();

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => [1 => ['enabled' => 1, 'start' => '', 'end' => '']],
    ])->assertSessionHasErrors('days.1.start');

    expect(WorkingHour::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a day that closes before it opens', function () {
    [$seller] = availabilitySeller();

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => [1 => ['enabled' => 1, 'start' => '17:00', 'end' => '09:00']],
    ])->assertSessionHasErrors('days.1.end');
});

it('shows the saved week back on the grid', function () {
    [$seller] = availabilitySeller();

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => [
            1 => ['enabled' => 1, 'start' => '08:00', 'end' => '14:00'],
            2 => ['enabled' => 0],
        ],
    ]);

    $html = actingAs($seller)->get(route('availability.index', ['staff' => null]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="08:00"')
        ->and($html)->toContain('value="14:00"')
        ->and($html)->toContain('Monday')
        ->and($html)->toContain('Sunday');
});

it('keeps store hours and staff hours apart', function () {
    [$seller, $page] = availabilitySeller();

    $staff = StaffMember::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => [1 => ['enabled' => 1, 'start' => '09:00', 'end' => '17:00']],
    ])->assertRedirect();

    // Only Monday, and only midday: the rest of the week falls back to shop hours.
    actingAs($seller)->put(route('availability.update', ['staff' => $staff->id]), [
        'days' => [
            1 => ['enabled' => 1, 'start' => '10:00', 'end' => '12:00'],
            2 => ['enabled' => 0],
        ],
    ])->assertRedirect();

    // Both schedules coexist; neither overwrote the other.
    expect(WorkingHour::whereNull('staff_member_id')->where('weekday', 1)->sole()->start_time)->toBe('09:00')
        ->and(WorkingHour::where('staff_member_id', $staff->id)->where('weekday', 1)->sole()->start_time)->toBe('10:00');
});

it('never lets one seller edit another seller\'s staff hours', function () {
    [$seller] = availabilitySeller();
    [$otherSeller, $otherPage] = availabilitySeller();

    $foreign = StaffMember::factory()->create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
    ]);

    actingAs($seller)
        ->put(route('availability.update', ['staff' => $foreign->id]), [
            'days' => [1 => ['enabled' => 1, 'start' => '00:00', 'end' => '23:59']],
        ])
        ->assertNotFound();

    expect(WorkingHour::withoutGlobalScopes()->count())->toBe(0);
});

it('never shows availability screens to a store seller', function () {
    [$seller] = sellerWithPage(['theme' => 'store']);

    actingAs($seller)->get(route('availability.index', ['staff' => null]))->assertNotFound();
    actingAs($seller)->get(route('staff.index'))->assertNotFound();
    actingAs($seller)->get(route('time-off.index'))->assertNotFound();
});

it('adds staff, assigns services and keeps their bookings when removed', function () {
    [$seller, $page] = availabilitySeller();

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->post(route('staff.store'), [
        'name' => 'Chidi Okafor',
        'bio' => 'Senior stylist.',
        'services' => [$service->id],
    ])->assertRedirect();

    $member = StaffMember::withoutGlobalScopes()->sole();

    expect($member->name)->toBe('Chidi Okafor')
        ->and($member->services->pluck('id')->all())->toBe([$service->id]);

    $booking = Booking::factory()->confirmed()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'service_id' => $service->id,
        'staff_member_id' => $member->id,
    ]);

    actingAs($seller)->put(route('availability.update', ['staff' => $member->id]), [
        'days' => [1 => ['enabled' => 1, 'start' => '09:00', 'end' => '17:00']],
    ]);

    actingAs($seller)->delete(route('staff.destroy', $member))->assertRedirect();

    // Their hours go, but the appointment they were booked on does not.
    expect(WorkingHour::withoutGlobalScopes()->count())->toBe(0)
        ->and(Booking::withoutGlobalScopes()->find($booking->id))->not->toBeNull();
});

it('ignores service ids belonging to another seller when assigning staff', function () {
    [$seller, $page] = availabilitySeller();
    [$otherSeller, $otherPage] = availabilitySeller();

    $foreign = Service::factory()->create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
    ]);

    actingAs($seller)->post(route('staff.store'), [
        'name' => 'Chidi Okafor',
        'services' => [$foreign->id],
    ])->assertRedirect();

    expect(StaffMember::withoutGlobalScopes()->sole()->services)->toHaveCount(0);
});

it('blocks dates off so the slots inside them vanish', function () {
    [$seller, $page] = availabilitySeller();

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'price_kobo' => 0,
    ]);

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => openWeek(),
    ]);

    $monday = '2026-11-16';

    $before = app(AvailabilityService::class)->slotsFor($service, Carbon::parse($monday, 'Africa/Lagos'));
    expect($before)->not->toBeEmpty();

    actingAs($seller)->post(route('time-off.store'), [
        'starts_on' => $monday,
        'ends_on' => $monday,
        'all_day' => '1',
        'reason' => 'Public holiday',
    ])->assertRedirect();

    expect(app(AvailabilityService::class)->slotsFor($service, Carbon::parse($monday, 'Africa/Lagos')))->toBeEmpty();

    // Only that day is affected.
    $tuesday = app(AvailabilityService::class)->slotsFor($service, Carbon::parse('2026-11-17', 'Africa/Lagos'));
    expect($tuesday)->not->toBeEmpty();

    actingAs($seller)->delete(route('time-off.destroy', TimeOff::sole()))->assertRedirect();

    expect(app(AvailabilityService::class)->slotsFor($service, Carbon::parse($monday, 'Africa/Lagos')))->not->toBeEmpty();
});

it('blocks dates off for one person without closing the whole shop', function () {
    [$seller, $page] = availabilitySeller();

    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'price_kobo' => 0,
    ]);

    $away = StaffMember::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->put(route('availability.update', ['staff' => null]), [
        'days' => openWeek(),
    ]);

    actingAs($seller)->post(route('time-off.store'), [
        'starts_on' => '2026-11-16',
        'ends_on' => '2026-11-16',
        'all_day' => '1',
        'staff_member_id' => $away->id,
    ])->assertRedirect();

    $availability = app(AvailabilityService::class);

    expect($availability->slotsFor($service, Carbon::parse('2026-11-16', 'Africa/Lagos'), $away))->toBeEmpty()
        // The shop itself is still open.
        ->and($availability->slotsFor($service, Carbon::parse('2026-11-16', 'Africa/Lagos')))->not->toBeEmpty();
});

it('refuses dates off that end before they start', function () {
    [$seller] = availabilitySeller();

    actingAs($seller)->post(route('time-off.store'), [
        'starts_on' => '2026-11-20',
        'ends_on' => '2026-11-18',
        'all_day' => '1',
    ])->assertSessionHasErrors('ends_on');

    expect(TimeOff::withoutGlobalScopes()->count())->toBe(0);
});

it('never removes another seller\'s dates off', function () {
    [$seller] = availabilitySeller();
    [$otherSeller, $otherPage] = availabilitySeller();

    $foreign = TimeOff::create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
    ]);

    actingAs($seller)->delete(route('time-off.destroy', $foreign))->assertNotFound();

    expect(TimeOff::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();
});

it('paginates the team list', function () {
    [$seller, $page] = availabilitySeller();

    StaffMember::factory()->count(21)->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->get(route('staff.index'))
        ->assertOk()
        ->assertSee('page=2');
});
