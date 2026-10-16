<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Actions\UpdateBookingStatus;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The service theme's version of the orders inbox: the same list, drawer and
 * status buttons, pointed at bookings.
 */
class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);
        $timezone = $this->timezone($page);

        if ($request->query('view') === 'week') {
            return $this->weekView($request, $page, $timezone);
        }

        $filter = $request->query('filter', 'upcoming');

        $bookings = $page->bookings()
            ->with(['service', 'staffMember'])
            ->when($filter === 'today', fn ($query) => $query->whereDate('starts_at', today($timezone)))
            ->when($filter === 'past', fn ($query) => $query->where('starts_at', '<', now()))
            ->when($filter === 'upcoming', fn ($query) => $query->where('starts_at', '>=', now()))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.bookings.index', [
            'page' => $page,
            'bookings' => $bookings,
            'activeFilter' => $filter,
            'timezone' => $timezone,
        ]);
    }

    /**
     * Seven columns, one per day, because "who is in on Thursday" is the question
     * a salon owner actually asks. Loaded in one query: a column at a time would
     * be seven round trips for one screen.
     */
    private function weekView(Request $request, StorePage $page, string $timezone): View
    {
        $start = Carbon::parse(
            (string) $request->query('week', ''),
            $timezone,
        )->startOfWeek();

        $end = $start->copy()->addDays(7);

        $bookings = $page->bookings()
            ->with(['service', 'staffMember'])
            ->where('starts_at', '>=', $start->copy()->utc())
            ->where('starts_at', '<', $end->copy()->utc())
            ->orderBy('starts_at')
            ->get();

        $days = collect(range(0, 6))->map(function (int $offset) use ($start, $bookings, $timezone) {
            $day = $start->copy()->addDays($offset);

            return [
                'date' => $day,
                'label' => $day->format('D j M'),
                'isToday' => $day->isSameDay(Carbon::now($timezone)),
                'bookings' => $bookings->filter(
                    fn (Booking $booking) => $booking->starts_at->timezone($timezone)->isSameDay($day)
                )->values(),
            ];
        });

        return view('dashboard.bookings.week', [
            'page' => $page,
            'days' => $days,
            'weekStart' => $start,
            'previousWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
            'timezone' => $timezone,
        ]);
    }

    public function show(Request $request, Booking $booking): View
    {
        $page = $this->page($request);

        abort_unless($booking->store_page_id === $page->id, 404);

        return view('dashboard.bookings.show', [
            'page' => $page,
            'booking' => $booking->load(['service', 'staffMember', 'history']),
        ]);
    }

    public function updateStatus(
        Request $request,
        Booking $booking,
        UpdateBookingStatus $updateBookingStatus,
    ): RedirectResponse {
        $page = $this->page($request);

        abort_unless($booking->store_page_id === $page->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(BookingStatus::class)],
        ]);

        $status = BookingStatus::from($data['status']);

        $updateBookingStatus->handle($booking, $status, $request->user());

        return back()->with('status', "Booking {$booking->reference} marked as {$status->label()}.");
    }

    /**
     * Cancelling a booking frees its slot. The cancellation window is enforced by
     * the policy, but a seller cancelling late on purpose is legitimate here, so
     * this path only refuses transitions the lifecycle does not allow.
     */
    public function cancel(
        Request $request,
        Booking $booking,
        UpdateBookingStatus $updateBookingStatus,
    ): RedirectResponse {
        $page = $this->page($request);

        abort_unless($booking->store_page_id === $page->id, 404);

        $updateBookingStatus->handle(
            $booking,
            BookingStatus::Cancelled,
            $request->user(),
            $request->input('note'),
        );

        return back()->with('status', "Booking {$booking->reference} cancelled. The slot is free again.");
    }

    private function page(Request $request): StorePage
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($page->theme === Theme::Service, 404);

        return $page;
    }

    private function timezone(StorePage $page): string
    {
        return $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');
    }
}
