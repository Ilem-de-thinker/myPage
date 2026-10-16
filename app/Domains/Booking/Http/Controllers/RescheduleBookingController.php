<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Actions\RescheduleBooking;
use App\Domains\Booking\Exceptions\OutsideCancellationWindow;
use App\Domains\Booking\Exceptions\SlotUnavailable;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Booking\Services\CancellationPolicy;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Moves a booking to another slot, used from the booking detail screen.
 */
class RescheduleBookingController
{
    public function show(
        Request $request,
        Booking $booking,
        CancellationPolicy $policy,
    ): View {
        abort_unless($booking->store_page_id === $this->pageId($request), 404);

        $service = $booking->service;

        $timezone = app(AvailabilityService::class)->timezoneFor($service);

        return view('dashboard.bookings.reschedule', [
            'page' => $this->page($request),
            'booking' => $booking->load('service'),
            'staff' => $booking->storePage->staffMembers()->orderBy('name')->get(),
            'dates' => collect(range(0, 20))->map(function (int $offset) use ($timezone) {
                $date = Carbon::now($timezone)->addDays($offset);

                return ['value' => $date->toDateString(), 'day' => $date->format('D j M')];
            }),
            'slots' => $this->slotsFor($booking, $request->query('date'), $request->query('staff')),
            'selectedDate' => $request->query('date'),
            'selectedStaff' => $request->query('staff'),
            'locked' => $policy->isLocked($booking),
            'hoursUntil' => $policy->hoursUntil($booking),
            'windowHours' => $policy->windowHoursFor($booking->storePage),
        ]);
    }

    public function store(
        Request $request,
        Booking $booking,
        RescheduleBooking $rescheduleBooking,
    ): RedirectResponse {
        abort_unless($booking->store_page_id === $this->pageId($request), 404);

        $data = $request->validate([
            'starts_at' => ['required', 'string'],
            'staff' => ['nullable', 'integer'],
        ]);

        try {
            $rescheduleBooking->handle(
                $booking,
                Carbon::parse($data['starts_at']),
                $request->user(),
            );
        } catch (OutsideCancellationWindow|SlotUnavailable $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        return redirect()
            ->route('bookings.show', $booking)
            ->with('status', "Booking {$booking->reference} moved.");
    }

    /**
     * The same endpoint the public widget uses, narrowed to the bookings seller
     * is allowed to move.
     *
     * @return Collection<int, array{starts_at: string, label: string}>
     */
    private function slotsFor(Booking $booking, ?string $date, ?string $staffId): Collection
    {
        if (! $date) {
            return collect();
        }

        $service = $booking->service;
        $availability = app(AvailabilityService::class);

        $staff = $staffId
            ? $booking->storePage->staffMembers()->whereKey($staffId)->first()
            : null;

        $timezone = $availability->timezoneFor($service);

        return $availability
            ->slotsFor($service, Carbon::parse($date, $timezone)->startOfDay(), $staff, (int) config('mypage.booking_slot_step_minutes', 30))
            // The slot the booking already occupies is not a move.
            ->reject(fn (Carbon $slot) => $slot->equalTo($booking->starts_at->setTimezone($timezone)))
            ->map(fn (Carbon $slot) => [
                'starts_at' => $slot->toIso8601String(),
                'label' => $slot->format('g:i A'),
            ])
            ->values();
    }

    private function pageId(Request $request): int
    {
        return $this->page($request)->getKey();
    }

    private function page(Request $request): StorePage
    {
        return $request->user()->storePages()->firstOrFail();
    }
}
