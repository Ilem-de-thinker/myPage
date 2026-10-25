<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\StoreEvent;
use App\Domains\Booking\Models\Booking;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use Illuminate\Support\Carbon;

final class PlatformAnalyticsSummary
{
    /**
     * @return array<string, mixed>
     */
    public function for(int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $now = now();
        $since = $now->copy()->subDays($days - 1)->startOfDay();
        $events = StoreEvent::query()->where('created_at', '>=', $since);
        $paidStatuses = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $status): bool => $status->countsTowardsLifetimeValue())
            ->map->value
            ->all();

        return [
            'days' => $days,
            'period' => ['from' => $since->toDateString(), 'to' => $now->toDateString()],
            'events' => (clone $events)->count(),
            'views' => (clone $events)->where('event', 'view')->count(),
            'listing_views' => (clone $events)->whereIn('event', ['product_view', 'service_view'])->count(),
            'directory_clicks' => (clone $events)->where('event', 'directory_click')->count(),
            'whatsapp_taps' => (clone $events)->where('event', 'whatsapp_tap')->count(),
            'unique_visitors' => (clone $events)->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
            'orders' => Order::withoutGlobalScopes()->where('created_at', '>=', $since)->count(),
            'paid_orders' => Order::withoutGlobalScopes()->where('created_at', '>=', $since)->whereIn('status', $paidStatuses)->count(),
            'revenue_kobo' => (int) Order::withoutGlobalScopes()->where('created_at', '>=', $since)->whereIn('status', $paidStatuses)->sum('total_kobo'),
            'bookings' => Booking::withoutGlobalScopes()->where('created_at', '>=', $since)->count(),
            'daily' => (clone $events)
                ->selectRaw("DATE(created_at) as day, COUNT(*) as events, SUM(CASE WHEN event = 'view' THEN 1 ELSE 0 END) as views, SUM(CASE WHEN event = 'directory_click' THEN 1 ELSE 0 END) as directory_clicks")
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row): array => [
                    'day' => $row->day,
                    'label' => Carbon::parse($row->day)->format('j M'),
                    'events' => (int) $row->events,
                    'views' => (int) $row->views,
                    'directory_clicks' => (int) $row->directory_clicks,
                ])
                ->all(),
            'top_sources' => (clone $events)
                ->whereNotNull('source')
                ->selectRaw('source, COUNT(*) as total')
                ->groupBy('source')
                ->orderByDesc('total')
                ->limit(10)
                ->pluck('total', 'source')
                ->map(fn ($value): int => (int) $value)
                ->all(),
        ];
    }
}
