<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Exceptions\SlotUnavailable;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Customers\Actions\UpsertCustomerFromBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reserves a slot for a visitor.
 *
 * Two buyers can reach this at the same instant with the same slot from two
 * different tabs. The availability check therefore runs again inside the
 * transaction, after the store page row has been locked, so the second one waits
 * and then sees the first one's hold.
 */
class CreateBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly UpsertCustomerFromBooking $upsertCustomerFromBooking,
        private readonly QuoteServiceFulfilment $fulfilment,
    ) {}

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, notes?: ?string}  $buyer
     */
    public function handle(
        Service $service,
        Carbon $startsAt,
        ?StaffMember $staff = null,
        array $buyer = [],
        string $source = 'web',
    ): Booking {
        $timezone = $this->availability->timezoneFor($service);

        // The visitor picks a wall clock time in the store's zone; everything
        // downstream is UTC.
        $start = $startsAt->copy()->setTimezone($timezone);
        $endsAt = $start->copy()->addMinutes($service->duration_min);

        // A travel fee is only owed when this service actually happens at the
        // buyer's place. The resolver applies the service's own mode first and
        // falls back to the store profile, so inheriting home service works
        // without editing every service.
        $travelFee = $this->fulfilment->handle($service)['travel_fee_kobo'];
        $serviceDue = $service->amountDueKobo();
        $dueKobo = $serviceDue + $travelFee;

        return DB::transaction(function () use ($service, $start, $endsAt, $staff, $buyer, $source, $dueKobo, $travelFee) {
            // Serialise every booking attempt for this store page, shared with
            // RescheduleBooking so the two cannot pass each other. Simple and
            // safe at Nigerian booking volumes; move to per-staff locks if two
            // sellers' traffic ever contends on one popular salon.
            StorePageForBooking::lock($service->store_page_id);

            // Re-checked under the lock rather than trusting the slot list the
            // visitor's page rendered minutes ago.
            throw_unless(
                $this->availability->isAvailable($service, $start, $staff),
                new SlotUnavailable,
            );

            $booking = Booking::withoutGlobalScopes()->create([
                'seller_id' => $service->seller_id,
                'store_page_id' => $service->store_page_id,
                'service_id' => $service->getKey(),
                'staff_member_id' => $staff?->getKey(),
                'reference' => Booking::generateReference(),
                'starts_at' => $start->copy()->utc(),
                'ends_at' => $endsAt->copy()->utc(),
                // The service's own price and deposit stay separate from the
                // travel fee: the deposit is what the seller asked for, and the
                // travel fee is a separate charge that is always paid now.
                'price_kobo' => $service->price_kobo,
                'deposit_kobo' => $service->depositKobo(),
                'travel_fee_kobo' => $travelFee,
                // What the buyer owes is the deposit, or the whole price when
                // they set no deposit, plus the travel fee. Nothing owed means
                // nothing to wait for, so the booking is confirmed on the spot.
                'status' => $dueKobo > 0 ? 'pending_payment' : 'confirmed',
                // A free booking holds nothing, so it gets no expiry.
                'expires_at' => $dueKobo > 0 ? now()->addMinutes((int) config('mypage.booking_hold_minutes', 15)) : null,
                'customer_name' => $buyer['name'] ?? null,
                'customer_email' => $buyer['email'] ?? null,
                'customer_phone' => $buyer['phone'] ?? null,
                'notes' => $buyer['notes'] ?? null,
                'source' => $source,
            ]);

            $booking->customer_id = $this->upsertCustomerFromBooking->handle($booking)?->getKey();
            $booking->save();

            $booking->history()->create([
                'from_status' => null,
                'to_status' => $booking->status,
                'note' => 'Booking created',
            ]);

            return $booking;
        });
    }
}
