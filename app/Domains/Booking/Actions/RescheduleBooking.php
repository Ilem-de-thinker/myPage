<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Exceptions\OutsideCancellationWindow;
use App\Domains\Booking\Exceptions\SlotUnavailable;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Booking\Services\CancellationPolicy;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moves a confirmed booking to another slot, re-checking availability under the
 * same row lock CreateBooking uses so two reschedules cannot collide.
 */
class RescheduleBooking
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly CancellationPolicy $policy,
    ) {}

    public function handle(Booking $booking, Carbon $newStart, ?Seller $by = null, ?StaffMember $staff = null): Booking
    {
        if ($this->policy->isLocked($booking)) {
            throw new OutsideCancellationWindow($this->policy->hoursUntil($booking));
        }

        $service = $booking->service;

        // A booking whose service has since been deleted cannot be moved: there
        // is no duration to move it to.
        if (! $service instanceof Service) {
            throw new SlotUnavailable('This booking no longer has a service to reschedule.');
        }

        $timezone = $this->availability->timezoneFor($service);

        $start = $newStart->copy()->setTimezone($timezone);
        $staff ??= $booking->staffMember;

        return DB::transaction(function () use ($booking, $service, $start, $staff, $by, $timezone) {
            StorePageForBooking::lock($booking->store_page_id);

            throw_unless(
                $this->availability->isAvailable($service, $start, $staff),
                new SlotUnavailable('That time is not free.'),
            );

            $booking->update([
                'staff_member_id' => $staff?->getKey(),
                'starts_at' => $start->copy()->utc(),
                'ends_at' => $start->copy()->addMinutes($service->duration_min)->utc(),
            ]);

            $booking->history()->create([
                'from_status' => $booking->status,
                'to_status' => $booking->status,
                'by_seller_id' => $by?->getKey(),
                'note' => 'Moved to '.$start->timezone($timezone)->format('D j M, g:i A'),
            ]);

            return $booking->fresh();
        });
    }
}
