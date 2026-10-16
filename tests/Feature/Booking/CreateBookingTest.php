<?php

use App\Domains\Booking\Actions\CreateBooking;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Exceptions\SlotUnavailable;
use App\Domains\Booking\Jobs\ExpirePendingBookings;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Catalog\Models\Product;
use App\Domains\Customers\Models\Customer;
use App\Domains\Orders\Actions\CreateOrderFromCheckout;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

/**
 * A store page open nine to five Lagos time, every day, with one service.
 *
 * @return array{0: Service, 1: StorePage}
 */
function bookableStore(array $serviceAttributes = [], array $pageAttributes = []): array
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

    $service = Service::factory()->create(array_merge([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'duration_min' => 60,
        'buffer_min' => 0,
    ], $serviceAttributes));

    return [$service, $page];
}

/**
 * A slot far enough ahead that the notice window never gets in the way.
 */
function futureSlot(string $time = '11:00', int $weeksAhead = 2): Carbon
{
    return Carbon::parse('2026-11-16 '.$time, 'Africa/Lagos')->addWeeks($weeksAhead);
}

it('books a free slot and confirms it outright', function () {
    [$service, $page] = bookableStore(['price_kobo' => 0]);

    $booking = app(CreateBooking::class)->handle(
        $service,
        futureSlot(),
        null,
        ['name' => 'Ada Buyer', 'phone' => '08031234567'],
    );

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->reference)->toStartWith('BK')
        ->and($booking->starts_at->timezone('UTC')->format('H:i'))->toBe('10:00') // 11:00 Lagos
        ->and($booking->ends_at->equalTo($booking->starts_at->copy()->addHour()))->toBeTrue()
        ->and($booking->expires_at)->toBeNull();
});

it('holds the slot for payment when a deposit is due', function () {
    [$service] = bookableStore(['price_kobo' => 1000000, 'deposit_type' => DepositType::Fixed, 'deposit_value' => 250000]);

    $booking = app(CreateBooking::class)->handle($service, futureSlot(), null, ['email' => 'a@b.co']);

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->deposit_kobo)->toBe(250000)
        ->and($booking->expires_at)->not->toBeNull()
        ->and($booking->expires_at->isFuture())->toBeTrue();
});

it('computes a percentage deposit', function () {
    [$service] = bookableStore(['price_kobo' => 1000000, 'deposit_type' => DepositType::Percent, 'deposit_value' => 30]);

    $booking = app(CreateBooking::class)->handle($service, futureSlot(), null, ['email' => 'a@b.co']);

    expect($booking->deposit_kobo)->toBe(300000);
});

it('never lets a deposit exceed the price', function () {
    [$service] = bookableStore(['price_kobo' => 100000, 'deposit_type' => DepositType::Fixed, 'deposit_value' => 900000]);

    $booking = app(CreateBooking::class)->handle($service, futureSlot(), null, ['email' => 'a@b.co']);

    expect($booking->deposit_kobo)->toBe(100000);
});

it('refuses a slot that is already taken', function () {
    [$service] = bookableStore();
    $create = app(CreateBooking::class);

    $create->handle($service, futureSlot('11:00'), null, ['email' => 'first@example.com']);

    expect(fn () => $create->handle($service, futureSlot('11:00'), null, ['email' => 'second@example.com']))
        ->toThrow(SlotUnavailable::class);

    expect(Booking::withoutGlobalScopes()->count())->toBe(1);
});

it('refuses a slot that overlaps an existing booking from any source', function () {
    [$service] = bookableStore();
    $create = app(CreateBooking::class);

    $create->handle($service, futureSlot('11:00'), null, ['email' => 'first@example.com']);

    // 11:30 would start halfway through the first appointment.
    expect(fn () => $create->handle($service, futureSlot('11:30'), null, ['email' => 'second@example.com']))
        ->toThrow(SlotUnavailable::class);

    // The first booking is holding the slot while it waits for payment, which is
    // exactly why the second one is refused.
    expect(Booking::withoutGlobalScopes()->sole()->status)->toBe(BookingStatus::PendingPayment);
});

it('still allows two different staff to take the same slot', function () {
    [$service, $page] = bookableStore();

    $first = StaffMember::factory()->create(['seller_id' => $service->seller_id, 'store_page_id' => $page->id]);
    $second = StaffMember::factory()->create(['seller_id' => $service->seller_id, 'store_page_id' => $page->id]);

    $create = app(CreateBooking::class);

    $create->handle($service, futureSlot(), $first, ['email' => 'a@example.com']);
    $create->handle($service, futureSlot(), $second, ['email' => 'b@example.com']);

    expect(Booking::withoutGlobalScopes()->count())->toBe(2);
});

it('refuses a slot outside opening hours', function () {
    [$service] = bookableStore();

    expect(fn () => app(CreateBooking::class)->handle($service, futureSlot('20:00'), null, ['email' => 'a@b.co']))
        ->toThrow(SlotUnavailable::class);
});

it('refuses a slot on a day the store is closed', function () {
    [$service] = bookableStore();

    WorkingHour::where('store_page_id', $service->store_page_id)->delete();

    expect(fn () => app(CreateBooking::class)->handle($service, futureSlot(), null, ['email' => 'a@b.co']))
        ->toThrow(SlotUnavailable::class);
});

it('refuses a slot inside the notice window', function () {
    [$service] = bookableStore();

    // An hour from now, when the minimum notice is an hour.
    $tooSoon = Carbon::now('Africa/Lagos')->addMinutes(30);

    expect(fn () => app(CreateBooking::class)->handle($service, $tooSoon, null, ['email' => 'a@b.co']))
        ->toThrow(SlotUnavailable::class);
});

it('frees the slot again once the hold expires', function () {
    [$service] = bookableStore(['price_kobo' => 500000, 'deposit_type' => DepositType::Fixed, 'deposit_value' => 100000]);
    $create = app(CreateBooking::class);

    $create->handle($service, futureSlot(), null, ['email' => 'a@example.com']);

    // Advance past the hold window and let the scheduled sweep run.
    $this->travel(20)->minutes();

    // The scheduler injects handle()'s dependency; a direct call has to ask for it.
    app()->call([app(ExpirePendingBookings::class), 'handle']);

    expect(Booking::withoutGlobalScopes()->sole()->status)->toBe(BookingStatus::Expired);

    // And the slot is genuinely bookable again.
    $create->handle($service, futureSlot(), null, ['email' => 'b@example.com']);

    expect(Booking::withoutGlobalScopes()->count())->toBe(2);
});

it('records the first move in the booking history', function () {
    [$service] = bookableStore(['price_kobo' => 0]);

    $booking = app(CreateBooking::class)->handle($service, futureSlot(), null, ['email' => 'a@b.co']);

    expect($booking->history()->sole()->to_status)->toBe(BookingStatus::Confirmed)
        ->and($booking->history()->sole()->from_status)->toBeNull();
});

it('adds a booking guest to the customer book and reuses them next time', function () {
    [$service] = bookableStore(['price_kobo' => 0]);
    $create = app(CreateBooking::class);

    $create->handle($service, futureSlot('09:00'), null, [
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ]);

    $customer = Customer::sole();

    expect($customer->name)->toBe('Ada Buyer')
        ->and($customer->phone)->toBe('+2348031234567')
        ->and($customer->booking_count)->toBe(1);

    // Same person, second booking: one customer, count of two.
    $create->handle($service, futureSlot('14:00'), null, [
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ]);

    expect(Customer::withoutGlobalScopes()->count())->toBe(1)
        ->and($customer->fresh()->booking_count)->toBe(2);
});

it('counts one buyer once even when a booking and an order share a phone number', function () {
    [$service, $page] = bookableStore(['price_kobo' => 0]);

    app(CreateBooking::class)->handle($service, futureSlot(), null, [
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ]);

    // The two actions never meet in the UI, because a seller runs one theme. The
    // identity rule still has to hold if a seller ever lists both.
    $product = Product::factory()->create([
        'seller_id' => $service->seller_id,
        'store_page_id' => $page->id,
        'status' => 'active',
    ]);

    app(CreateOrderFromCheckout::class)->handle(
        $page,
        [['product_id' => $product->id, 'quantity' => 1]],
        ['email' => 'other@example.com', 'phone' => '08031234567'],
    );

    $customer = Customer::sole();

    expect($customer->booking_count)->toBe(1)
        ->and($customer->order_count)->toBe(1);
});

it('never books another store page\'s service', function () {
    [$service] = bookableStore();

    $foreign = Service::factory()->create([
        'duration_min' => 60,
        'store_page_id' => StorePage::factory()->create(['theme' => 'service', 'status' => 'published'])->id,
    ]);

    // The foreign service has no working hours of its own, so nothing is bookable.
    expect(fn () => app(CreateBooking::class)->handle($foreign, futureSlot(), null, ['email' => 'a@b.co']))
        ->toThrow(SlotUnavailable::class);
});
