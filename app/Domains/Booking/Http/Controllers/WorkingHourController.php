<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Actions\UpdateWorkingHours;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The weekly schedule a seller edits. Store-wide hours gate every booking on the
 * page; a named person's hours override them when they have any.
 */
class WorkingHourController extends Controller
{
    public function index(Request $request, ?StaffMember $staff = null): View
    {
        $page = $this->page($request);

        abort_if($staff && $staff->store_page_id !== $page->id, 404);

        return view('dashboard.availability.index', [
            'page' => $page,
            'staff' => $staff,
            'staffOptions' => $page->staffMembers()->orderBy('name')->get(),
            'week' => $this->weekFor($page, $staff),
            'timezone' => $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos'),
        ]);
    }

    public function update(
        Request $request,
        UpdateWorkingHours $updateWorkingHours,
        ?StaffMember $staff = null,
    ): RedirectResponse {
        $page = $this->page($request);

        abort_if($staff && $staff->store_page_id !== $page->id, 404);

        $data = $request->validate([
            'days' => ['required', 'array'],
            'days.*.enabled' => ['sometimes', 'boolean'],
            'days.*.start' => ['nullable', 'string', 'max:5'],
            'days.*.end' => ['nullable', 'string', 'max:5'],
        ]);

        $week = [];

        foreach (range(1, 7) as $weekday) {
            $day = $data['days'][$weekday] ?? null;

            if (! $day || ! filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $week[$weekday] = ['enabled' => false];

                continue;
            }

            $start = $day['start'] ?? null;
            $end = $day['end'] ?? null;

            // A day switched on with no usable times is a mistake worth naming,
            // not silently storing as closed.
            if (! $this->isTime($start) || ! $this->isTime($end)) {
                throw ValidationException::withMessages([
                    "days.{$weekday}.start" => 'Give this day both a start and an end time, or switch it off.',
                ]);
            }

            if ($start >= $end) {
                throw ValidationException::withMessages([
                    "days.{$weekday}.end" => 'The closing time has to be after the opening time.',
                ]);
            }

            $week[$weekday] = ['enabled' => true, 'start' => $start, 'end' => $end];
        }

        $staff
            ? $updateWorkingHours->handleStaffMember($page, $staff, $week)
            : $updateWorkingHours->handleStorePage($page, $week);

        return back()->with('status', $staff
            ? "{$staff->name}'s hours saved."
            : 'Opening hours saved. Customers can book these times now.');
    }

    /**
     * The grid always shows all seven days, filled in from whatever rows exist
     * so the seller edits a complete week rather than a sparse one.
     *
     * @return array<int, array{enabled: bool, start: ?string, end: ?string}>
     */
    private function weekFor(StorePage $page, ?StaffMember $staff): array
    {
        $rows = WorkingHour::where('store_page_id', $page->getKey())
            ->where('staff_member_id', $staff?->getKey())
            ->orderBy('start_time')
            ->get()
            ->groupBy('weekday');

        $week = [];

        foreach (range(1, 7) as $weekday) {
            $day = $rows->get($weekday);

            if ($day === null || $day->isEmpty()) {
                $week[$weekday] = ['enabled' => false, 'start' => '09:00', 'end' => '17:00'];

                continue;
            }

            // Split days show only their widest window; the grid edits one window
            // per day, and existing splits are left untouched rather than merged.
            $first = $day->first();
            $last = $day->last();

            $week[$weekday] = [
                'enabled' => true,
                'start' => $first->start_time,
                'end' => $last->end_time,
            ];
        }

        return $week;
    }

    private function isTime(?string $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    private function page(Request $request): StorePage
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($page->theme === Theme::Service, 404);

        return $page;
    }
}
