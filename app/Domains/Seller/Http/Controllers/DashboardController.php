<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Analytics\Services\AnalyticsSummary;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Services\DashboardStats;
use App\Domains\Seller\Services\PublishReadiness;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardStats $stats,
        PublishReadiness $readiness,
        AnalyticsSummary $analytics,
        SubscriptionAvailability $availability,
    ): View {
        $page = $request->user()->storePages()->first();
        $subscription = $availability->enabled()
            ? Subscription::with('plan')->where('seller_id', $request->user()->getKey())->first()
            : null;

        return view('dashboard.index', [
            'page' => $page,
            // Every figure on the card row, so the template holds no logic of its own.
            'stats' => $page ? $stats->for($page) : null,
            'analytics' => $page ? $analytics->for($page) : null,
            'checks' => $page ? $readiness->checks($page) : [],
            'subscription' => $subscription,
            'merchantStatus' => $subscription?->merchantStatus(),
        ]);
    }
}
