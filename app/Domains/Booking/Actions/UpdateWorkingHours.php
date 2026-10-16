<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a schedule wholesale from the weekly grid the seller edits.
 *
 * Wholesale rather than diffed, because the grid is the source of truth: what
 * is saved is exactly what is on screen. It runs in a transaction so a half
 * written schedule can never be served to a visitor mid-save.
 */
class UpdateWorkingHours
{
    /**
     * @param  array<int, array{enabled: bool, start?: ?string, end?: ?string, segments?: list<array{start: string, end: string}>}>  $week
     * @param  list<int>  $weekdays  ISO 1 (Monday) to 7 (Sunday)
     */
    public function handleStorePage(StorePage $page, array $week, array $weekdays = [1, 2, 3, 4, 5, 6, 7]): void
    {
        $this->replace($page->getKey(), null, $week, $weekdays);
    }

    /**
     * @param  array<int, array{enabled: bool, start?: ?string, end?: ?string, segments?: list<array{start: string, end: string}>}>  $week
     * @param  list<int>  $weekdays
     */
    public function handleStaffMember(StorePage $page, StaffMember $staff, array $week, array $weekdays = [1, 2, 3, 4, 5, 6, 7]): void
    {
        $this->replace($page->getKey(), $staff->getKey(), $week, $weekdays);
    }

    /**
     * @param  array<int, array{enabled: bool, start?: ?string, end?: ?string, segments?: list<array{start: string, end: string}>}>  $week
     * @param  list<int>  $weekdays
     */
    private function replace(int $storePageId, ?int $staffId, array $week, array $weekdays): void
    {
        DB::transaction(function () use ($storePageId, $staffId, $week, $weekdays) {
            WorkingHour::where('store_page_id', $storePageId)
                ->where('staff_member_id', $staffId)
                ->whereIn('weekday', $weekdays)
                ->delete();

            foreach ($weekdays as $weekday) {
                $day = $week[$weekday] ?? null;

                if (! $day || ! ($day['enabled'] ?? false)) {
                    continue;
                }

                foreach ($this->segmentsFor($day) as $segment) {
                    WorkingHour::create([
                        'store_page_id' => $storePageId,
                        'staff_member_id' => $staffId,
                        'weekday' => $weekday,
                        'start_time' => $segment['start'],
                        'end_time' => $segment['end'],
                    ]);
                }
            }
        });
    }

    /**
     * A day can be a single window or split into a lunch break, so both shapes
     * land on the same list of segments.
     *
     * @param  array{enabled: bool, start?: ?string, end?: ?string, segments?: list<array{start: string, end: string}>}  $day
     * @return list<array{start: string, end: string}>
     */
    private function segmentsFor(array $day): array
    {
        if (! empty($day['segments'])) {
            return array_values(array_filter(
                $day['segments'],
                fn (array $segment) => ! empty($segment['start']) && ! empty($segment['end']),
            ));
        }

        if (! empty($day['start']) && ! empty($day['end'])) {
            return [['start' => $day['start'], 'end' => $day['end']]];
        }

        return [];
    }
}
