<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\TimeOff;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out which slots a visitor may still book.
 *
 * Everything here is reasoned in the store's own timezone and only converted to
 * UTC at the edges, because a salon that opens at 09:00 in Lagos must open at
 * 09:00 in Lagos on the day the slot list is asked for, not at whatever UTC
 * offset the query happened to land on.
 */
class AvailabilityService
{
    /**
     * Slots that begin on the given day, in the store's timezone.
     *
     * @return Collection<int, Carbon> start times, ascending
     */
    public function slotsFor(
        Service $service,
        Carbon $date,
        ?StaffMember $staff = null,
        int $stepMinutes = 30,
    ): Collection {
        $timezone = $this->timezoneFor($service);

        // Start of the day in the store's zone; the query bounds are UTC.
        $day = $date->copy()->setTimezone($timezone)->startOfDay();
        $weekday = $day->dayOfWeekIso;

        $notBefore = now()->addMinutes($this->minNoticeMinutes($service));

        return $this->hoursFor($service, $weekday, $staff)
            ->flatMap(function (WorkingHour $hour) use ($day, $service, $staff, $stepMinutes, $notBefore, $timezone) {
                $slots = collect();

                $open = $day->copy()->setTimeFromTimeString($hour->start_time);
                $close = $day->copy()->setTimeFromTimeString($hour->end_time);

                $step = max(1, $stepMinutes);

                // The cursor is where the *appointment* starts. buffer_min is
                // clearance the seller needs after it, so it shortens the day by
                // the service duration plus that buffer, not by the duration alone.
                $stride = $service->duration_min + $service->buffer_min;

                while ($open->copy()->addMinutes($stride)->lte($close)) {
                    if ($this->isBookable($service, $staff, $open, $notBefore, $timezone)) {
                        $slots->push($open->copy());
                    }

                    $open = $open->copy()->addMinutes($step);
                }

                return $slots;
            })
            ->unique(fn (Carbon $slot) => $slot->format('c'))
            ->sort()
            ->values();
    }

    /**
     * Whether this exact slot is still open, used by CreateBooking to re-check
     * under the row lock rather than trusting a stale page render.
     */
    public function isAvailable(Service $service, Carbon $start, ?StaffMember $staff = null): bool
    {
        $timezone = $this->timezoneFor($service);

        return $this->slotsFor($service, $start, $staff)
            ->contains(fn (Carbon $slot) => $slot->equalTo($start->setTimezone($timezone)));
    }

    /**
     * @return Collection<int, WorkingHour>
     */
    private function hoursFor(Service $service, int $weekday, ?StaffMember $staff): Collection
    {
        // A named member is governed by their own hours when they have any, and
        // by the business hours when they have none.
        if ($staff) {
            $own = $this->scopedHours($service->store_page_id, $weekday, $staff->getKey());

            if ($own->isNotEmpty()) {
                return $own;
            }
        }

        // With nobody chosen, the page advertises the business's own opening
        // hours. One person's Tuesday afternoon must never open the whole shop
        // to visitors who have not picked anyone.
        return $this->scopedHours($service->store_page_id, $weekday, null);
    }

    /**
     * @return Collection<int, WorkingHour>
     */
    private function scopedHours(int $storePageId, int $weekday, ?int $staffId): Collection
    {
        return WorkingHour::withoutGlobalScopes()
            ->where('store_page_id', $storePageId)
            ->where('weekday', $weekday)
            ->when(
                $staffId,
                fn ($query) => $query->where('staff_member_id', $staffId),
                fn ($query) => $query->whereNull('staff_member_id'),
            )
            ->orderBy('start_time')
            ->get();
    }

    private function isBookable(
        Service $service,
        ?StaffMember $staff,
        Carbon $start,
        Carbon $notBefore,
        string $timezone,
    ): bool {
        // Slots are offered in the store's zone but stored and compared in UTC.
        $startUtc = $start->copy()->setTimezone('UTC');
        $endsUtc = $startUtc->copy()->addMinutes($service->duration_min);

        // The buffer is dead time the seller needs, so an existing booking only
        // clashes if it runs into the start of this one or starts during ours.
        $blockedFrom = $startUtc->copy()->subMinutes($service->buffer_min);
        $blockedTo = $endsUtc->copy()->addMinutes($service->buffer_min);

        if ($start->lt($notBefore)) {
            return false;
        }

        if ($this->hasBooking($service, $staff, $blockedFrom, $blockedTo)) {
            return false;
        }

        return ! $this->hasTimeOff($service, $staff, $startUtc, $endsUtc);
    }

    private function hasBooking(Service $service, ?StaffMember $staff, Carbon $from, Carbon $to): bool
    {
        return Booking::withoutGlobalScopes()
            ->forPage($service->store_page_id)
            ->scopedTo($staff)
            ->occupyingSlot()
            // Half open: a booking ending exactly when this one starts is fine.
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->exists();
    }

    private function hasTimeOff(Service $service, ?StaffMember $staff, Carbon $from, Carbon $to): bool
    {
        return TimeOff::withoutGlobalScopes()
            ->where('store_page_id', $service->store_page_id)
            ->scopedTo($staff)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->exists();
    }

    public function timezoneFor(Service $service): string
    {
        $storePage = $service->relationLoaded('storePage')
            ? $service->storePage
            : StorePage::find($service->store_page_id);

        return $storePage?->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');
    }

    /**
     * How far ahead a visitor must book. A slot that starts in ten minutes is not
     * a booking, it is a queue.
     */
    private function minNoticeMinutes(Service $service): int
    {
        $storePage = $service->relationLoaded('storePage')
            ? $service->storePage
            : StorePage::find($service->store_page_id);

        return (int) ($storePage?->settings['min_notice_minutes']
            ?? config('mypage.booking_min_notice_minutes', 60));
    }
}
