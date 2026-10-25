<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Analytics\Services\AnalyticsSummary;
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
    ): View {
        $page = $request->user()->storePages()->first();

        return view('dashboard.index', [
            'page' => $page,
            // Every figure on the card row, so the template holds no logic of its own.
            'stats' => $page ? $stats->for($page) : null,
            'analytics' => $page ? $analytics->for($page) : null,
            'checks' => $page ? $readiness->checks($page) : [],
        ]);
    }
}
