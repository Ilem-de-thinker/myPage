<?php

namespace App\Domains\Seller\Services;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Customers\Models\Customer;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The numbers on the dashboard home. Each figure is scoped to one store page by
 * an explicit where clause rather than the tenant global scope, so the same code
 * reads the same whether it is called from a request, a test or a command.
 *
 * A store page sells products and takes orders; a service page sells appointments
 * and takes bookings. The key names stay theme neutral and the view supplies the
 * words, so the card row reads correctly for either theme.
 */
class DashboardStats
{
    /**
     * @return array{
     *     activity_this_week: int,
     *     activity_previous_week: int,
     *     earned_this_week_kobo: int,
     *     customers_total: int,
     *     new_customers_this_week: int,
     *     pending_count: int,
     *     pending_value_kobo: int
     * }
     */
    public function for(StorePage $page): array
    {
        $thisWeek = now()->startOfWeek();
        $lastWeek = $thisWeek->copy()->subWeek();

        $customers = [
            'customers_total' => Customer::where('seller_id', $page->seller_id)->count(),
            'new_customers_this_week' => Customer::where('seller_id', $page->seller_id)
                ->where('created_at', '>=', $thisWeek)
                ->count(),
        ];

        return $page->theme === Theme::Service
            ? $customers + $this->forServicePage($page, $thisWeek, $lastWeek)
            : $customers + $this->forStorePage($page, $thisWeek, $lastWeek);
    }

    /**
     * Only money the seller actually holds counts as revenue; a pending or
     * cancelled order is not theirs to spend.
     *
     * @return array{activity_this_week: int, activity_previous_week: int, earned_this_week_kobo: int, pending_count: int, pending_value_kobo: int}
     */
    private function forStorePage(StorePage $page, Carbon $thisWeek, Carbon $lastWeek): array
    {
        $earned = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $status) => $status->countsTowardsLifetimeValue())
            ->map(fn (OrderStatus $status) => $status->value)
            ->all();

        return [
            'activity_this_week' => $this->countBetween(
                Order::where('store_page_id', $page->getKey()),
                $thisWeek,
            ),
            'activity_previous_week' => $this->countBetween(
                Order::where('store_page_id', $page->getKey()),
                $lastWeek,
                $thisWeek,
            ),
            'earned_this_week_kobo' => (int) Order::where('store_page_id', $page->getKey())
                ->where('created_at', '>=', $thisWeek)
                ->whereIn('status', $earned)
                ->sum('total_kobo'),
            'pending_count' => AbandonedCart::where('store_page_id', $page->getKey())->open()->count(),
            'pending_value_kobo' => (int) AbandonedCart::where('store_page_id', $page->getKey())->open()->sum('total_kobo'),
        ];
    }

    /**
     * The booking mirror of the same row: requests in this week against last week,
     * money earned from appointments the seller has kept, and the appointments
     * still ahead of them.
     *
     * @return array{activity_this_week: int, activity_previous_week: int, earned_this_week_kobo: int, pending_count: int, pending_value_kobo: int}
     */
    private function forServicePage(StorePage $page, Carbon $thisWeek, Carbon $lastWeek): array
    {
        $earned = collect(BookingStatus::cases())
            ->filter(fn (BookingStatus $status) => $status->countsTowardsLifetimeValue())
            ->map(fn (BookingStatus $status) => $status->value)
            ->all();

        $upcoming = Booking::where('store_page_id', $page->getKey())
            ->whereIn('status', BookingStatus::openStatuses())
            ->where('starts_at', '>=', now());

        return [
            'activity_this_week' => $this->countBetween(
                Booking::where('store_page_id', $page->getKey()),
                $thisWeek,
            ),
            'activity_previous_week' => $this->countBetween(
                Booking::where('store_page_id', $page->getKey()),
                $lastWeek,
                $thisWeek,
            ),
            'earned_this_week_kobo' => $this->dueTotal(
                Booking::where('store_page_id', $page->getKey())
                    ->where('created_at', '>=', $thisWeek)
                    ->whereIn('status', $earned)
            ),
            'pending_count' => (clone $upcoming)->count(),
            'pending_value_kobo' => $this->dueTotal($upcoming),
        ];
    }

    private function countBetween(Builder $query, Carbon $from, ?Carbon $to = null): int
    {
        return $query->where('created_at', '>=', $from)
            ->when($to, fn (Builder $query) => $query->where('created_at', '<', $to))
            ->count();
    }

    /**
     * What the buyer owes per booking, summed in PHP so the deposit-or-full-price
     * rule lives on the model instead of being duplicated as SQL.
     */
    private function dueTotal(Builder $query): int
    {
        return (int) $query->get()->sum(fn (Booking $booking) => $booking->amountDueKobo());
    }
}
