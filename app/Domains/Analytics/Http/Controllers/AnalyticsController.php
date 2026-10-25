<?php

namespace App\Domains\Analytics\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Analytics\Services\AnalyticsSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsSummary $summary): View
    {
        $page = $request->user()->storePages()->firstOrFail();
        $days = (int) $request->query('days', 30);
        $metrics = $summary->for($page, $days);

        return view('dashboard.analytics.index', compact('page', 'metrics', 'days'));
    }

    public function digest(Request $request, AiManager $ai, AnalyticsSummary $summary): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $days = (int) $request->query('days', 7);
        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $result = $ai->run('insights_digest', ['period' => 'last_'.$days.'_days', 'metrics' => $summary->for($page, $days)], $page);

        return response()->json(['result' => $result]);
    }

    public function ask(Request $request, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $data = $request->validate(['question' => ['required', 'string', 'max:1000']]);

        return response()->json(['result' => $ai->run('ask_your_data', ['question' => $data['question']], $page)]);
    }
}
