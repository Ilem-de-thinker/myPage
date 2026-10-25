<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Analytics\Services\PlatformAnalyticsSummary;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class AdminAnalyticsController extends Controller
{
    public function __invoke(Request $request, PlatformAnalyticsSummary $summary): View
    {
        $days = (int) $request->query('days', 30);

        return view('admin.analytics', [
            'metrics' => $summary->for($days),
            'days' => $days,
        ]);
    }
}
