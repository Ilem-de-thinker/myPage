<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\StoreEvent;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

final class AnalyticsSummary
{
    /**
     * Build the seller-facing seven-day analytics view for one store page.
     * Events are deliberately scoped by store_page_id at the query boundary.
     *
     * @return array<string, mixed>
     */
    public function for(StorePage $page, int $days = 30): array
    {
        $now = now();
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = $now->copy()->subDays($days - 1)->startOfDay();
        $events = StoreEvent::query()
            ->where('store_page_id', $page->getKey())
            ->where('created_at', '>=', $since);

        $topProducts = (clone $events)
            ->where('event', 'product_view')
            ->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) as views')
            ->groupBy('product_id')
            ->orderByDesc('views')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => ['product_id' => (int) $row->product_id, 'views' => (int) $row->views])
            ->all();

        $topServices = (clone $events)
            ->where('event', 'service_view')
            ->whereNotNull('service_id')
            ->selectRaw('service_id, COUNT(*) as views')
            ->groupBy('service_id')
            ->orderByDesc('views')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => ['service_id' => (int) $row->service_id, 'views' => (int) $row->views])
            ->all();

        $productNames = Product::query()->whereIn('id', array_column($topProducts, 'product_id'))->pluck('name', 'id');
        $serviceNames = Service::query()->whereIn('id', array_column($topServices, 'service_id'))->pluck('name', 'id');
        $directoryEvents = (clone $events)->where('event', 'directory_click')->get(['product_id', 'service_id', 'metadata']);
        $directorySources = $directoryEvents->groupBy(fn (StoreEvent $event): string => (string) data_get($event->metadata, 'directory_source', 'directory'))->map->count()->sortDesc()->take(5)->all();
        $directoryQueries = $directoryEvents->map(fn (StoreEvent $event): string => trim((string) data_get($event->metadata, 'query')))->filter()->countBy()->sortDesc()->take(5)->all();
        $sourceCounts = (clone $events)->whereNotNull('source')->selectRaw('source, COUNT(*) as total')->groupBy('source')->orderByDesc('total')->limit(8)->pluck('total', 'source')->map(fn ($value): int => (int) $value)->all();
        $deviceCounts = (clone $events)->whereNotNull('device')->selectRaw('device, COUNT(*) as total')->groupBy('device')->pluck('total', 'device')->map(fn ($value): int => (int) $value)->all();
        $orders = Order::query()->where('store_page_id', $page->getKey())->where('created_at', '>=', $since);
        $bookings = Booking::query()->where('store_page_id', $page->getKey())->where('created_at', '>=', $since);
        $paidStatuses = collect(OrderStatus::cases())->filter(fn (OrderStatus $status): bool => $status->countsTowardsLifetimeValue())->map->value->all();
        $bookingStatuses = [BookingStatus::Confirmed->value, BookingStatus::Completed->value];

        return [
            'period' => ['from' => $since->toDateString(), 'to' => $now->toDateString()],
            'days' => $days,
            'views' => (clone $events)->where('event', 'view')->count(),
            'product_views' => (clone $events)->where('event', 'product_view')->count(),
            'service_views' => (clone $events)->where('event', 'service_view')->count(),
            'whatsapp_taps' => (clone $events)->where('event', 'whatsapp_tap')->count(),
            'enquiries' => (clone $events)->where('event', 'enquiry')->count(),
            'directory_clicks' => $directoryEvents->count(),
            'directory_sources' => $directorySources,
            'directory_queries' => $directoryQueries,
            'sources' => $sourceCounts,
            'devices' => $deviceCounts,
            'orders' => (clone $orders)->count(),
            'paid_orders' => (clone $orders)->whereIn('status', $paidStatuses)->count(),
            'revenue_kobo' => (int) (clone $orders)->whereIn('status', $paidStatuses)->sum('total_kobo'),
            'bookings' => (clone $bookings)->count(),
            'confirmed_bookings' => (clone $bookings)->whereIn('status', $bookingStatuses)->count(),
            'conversion_rate' => $this->rate((clone $orders)->whereIn('status', $paidStatuses)->count(), (clone $events)->where('event', 'view')->count()),
            'unique_visitors' => (clone $events)->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
            'top_products' => array_map(fn (array $item): array => [...$item, 'name' => $productNames[$item['product_id']] ?? 'Product'], $topProducts),
            'top_services' => array_map(fn (array $item): array => [...$item, 'name' => $serviceNames[$item['service_id']] ?? 'Service'], $topServices),
            'daily' => (clone $events)
                ->selectRaw("DATE(created_at) as day, SUM(CASE WHEN event = 'view' THEN 1 ELSE 0 END) as views, SUM(CASE WHEN event IN ('product_view', 'service_view') THEN 1 ELSE 0 END) as listing_views, SUM(CASE WHEN event = 'whatsapp_tap' THEN 1 ELSE 0 END) as whatsapp_taps, SUM(CASE WHEN event = 'directory_click' THEN 1 ELSE 0 END) as directory_clicks")
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row): array => ['day' => $row->day, 'label' => Carbon::parse($row->day)->format('j M'), 'views' => (int) $row->views, 'listing_views' => (int) $row->listing_views, 'whatsapp_taps' => (int) $row->whatsapp_taps, 'directory_clicks' => (int) $row->directory_clicks])
                ->all(),
        ];
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }
}
