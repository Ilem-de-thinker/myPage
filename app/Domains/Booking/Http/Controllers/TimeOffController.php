<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Models\TimeOff;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Holidays and one-off closures. Recurring hours live in WorkingHourController;
 * this is for the dates that are not every week.
 */
class TimeOffController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);
        $timezone = $this->timezone($page);

        $blocks = $page->timeOff()
            ->with('staffMember')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->paginate(20);

        return view('dashboard.availability.time-off', [
            'page' => $page,
            'blocks' => $blocks,
            'staffOptions' => $page->staffMembers()->orderBy('name')->get(),
            'timezone' => $timezone,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = $this->page($request);

        $data = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d'],
            'all_day' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'staff_member_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $timezone = $this->timezone($page);

        try {
            $startsOn = Carbon::parse($data['starts_on'], $timezone)->startOfDay();
            $endsOn = Carbon::parse($data['ends_on'], $timezone);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['starts_on' => 'Those dates are not valid.']);
        }

        if ($endsOn->lt($startsOn)) {
            throw ValidationException::withMessages([
                'ends_on' => 'The last day has to be on or after the first day.',
            ]);
        }

        $allDay = $request->boolean('all_day');

        // A part-day block still runs to the end of the last day unless a closing
        // time is given, otherwise a morning-off closure would swallow the evening.
        try {
            $startsAt = $allDay ? $startsOn : $startsOn->copy()->setTimeFromTimeString(($data['start_time'] ?? null) ?: '00:00');
            $endsAt = $allDay
                ? $endsOn->copy()->endOfDay()
                : $endsOn->copy()->setTimeFromTimeString(($data['end_time'] ?? null) ?: '23:59');
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['end_time' => 'Those times are not valid.']);
        }

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'end_time' => 'The closing time has to be after the opening time.',
            ]);
        }

        $staff = null;

        if (! empty($data['staff_member_id'])) {
            $staff = $page->staffMembers()->whereKey($data['staff_member_id'])->first();

            abort_unless($staff, 404);
        }

        $page->timeOff()->create([
            'seller_id' => $page->seller_id,
            'staff_member_id' => $staff?->getKey(),
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'reason' => ($data['reason'] ?? null) ?: null,
        ]);

        return back()->with('status', 'Dates blocked off.');
    }

    public function destroy(Request $request, TimeOff $timeOff): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($timeOff->store_page_id === $page->id, 404);

        $timeOff->delete();

        return back()->with('status', 'Those dates are open again.');
    }

    private function timezone(StorePage $page): string
    {
        return $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');
    }

    private function page(Request $request): StorePage
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($page->theme === Theme::Service, 404);

        return $page;
    }
}
