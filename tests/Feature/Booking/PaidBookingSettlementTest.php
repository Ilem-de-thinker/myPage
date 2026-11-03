<?php

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Models\Transaction;
use Illuminate\Support\Carbon;

use function Pest\Laravel\post;

/**
 * A paid deposit holds the slot: when the gateway confirms, the booking must
 * become Confirmed on its own. Before SettleBookingAfterPayment the money
 * landed and the hold expired fifteen minutes later.
 */
it('confirms the booking when the deposit payment is verified', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    $service->update([
        'price_kobo' => 1500000,
        'deposit_type' => DepositType::Fixed,
        'deposit_value' => 300000,
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

    expect($booking->status)->toBe(BookingStatus::PendingPayment);

    $transaction = Transaction::where('payable_type', 'booking')->sole();

    app(ConfirmTransaction::class)->handle($transaction->reference);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->expires_at)->toBeNull()
        ->and($booking->fresh()->history()->where('to_status', BookingStatus::Confirmed->value)->count())->toBe(1);

    // A replayed confirmation does not double-apply or error.
    app(ConfirmTransaction::class)->handle($transaction->reference);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->history()->where('to_status', BookingStatus::Confirmed->value)->count())->toBe(1);
});

it('leaves the booking pending when the deposit is underpaid', function () {
    [$service, $page] = bookablePage('x');
    $date = openDate();

    $service->update([
        'price_kobo' => 1500000,
        'deposit_type' => DepositType::Fixed,
        'deposit_value' => 300000,
    ]);

    config([
        'services.paystack.secret' => 'sk_test',
        'mypage.gateway' => 'paystack',
    ]);
    fakePaystack(['amount_kobo' => 100]);

    post(storeUrl($page->slug, '/book'), [
        'service' => $service->id,
        'starts_at' => Carbon::parse($date.' 11:00', 'Africa/Lagos')->toIso8601String(),
        'name' => 'Ada Buyer',
        'phone' => '08031234567',
    ])->assertRedirect('https://checkout.paystack.com/test-checkout');

    $booking = Booking::withoutGlobalScopes()->sole();
    $transaction = Transaction::where('payable_type', 'booking')->sole();

    app(ConfirmTransaction::class)->handle($transaction->reference);

    expect($booking->fresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($transaction->fresh()->status->value)->toBe('failed');
});
