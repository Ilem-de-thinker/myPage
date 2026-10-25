<?php

namespace App\Domains\Admin\Services;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

/**
 * Platform numbers for the admin home. Each one is a separate count so the panel
 * stays cheap: no loops, no per-row aggregates.
 *
 * There is no commission line because the platform takes none: a buyer pays the
 * seller's own gateway account, and our revenue is subscriptions only.
 */
class PlatformMetrics
{
    /**
     * @return array{
     *     gmv_kobo: int,
     *     orders: int,
     *     paid_orders: int,
     *     stores: int,
     *     stores_by_theme: array<string, int>,
     *     suspended_stores: int,
     *     sellers: int,
     *     active_subscriptions: int
     * }
     */
    public function snapshot(): array
    {
        // Money that actually settled, not money that was asked for.
        $earned = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $status) => $status->countsTowardsLifetimeValue())
            ->map(fn (OrderStatus $status) => $status->value)
            ->all();

        // The seller scope is for a seller's own dashboards; admin numbers are the whole platform.
        $sales = Order::withoutGlobalScopes()->whereIn('status', $earned);

        return [
            'gmv_kobo' => (int) (clone $sales)->sum('total_kobo'),
            'orders' => Order::withoutGlobalScopes()->count(),
            'paid_orders' => (clone $sales)->count(),
            'stores' => StorePage::withoutGlobalScopes()->count(),
            'stores_by_theme' => StorePage::withoutGlobalScopes()
                ->selectRaw('theme, count(*) as total')
                ->groupBy('theme')
                ->pluck('total', 'theme')
                ->all(),
            'suspended_stores' => StorePage::withoutGlobalScopes()
                ->where('status', StoreStatus::Suspended->value)
                ->count(),
            'sellers' => Seller::withoutGlobalScopes()->count(),
            'active_subscriptions' => Subscription::withoutGlobalScopes()
                ->where('status', SubscriptionStatus::Active->value)
                ->count(),
        ];
    }
}
