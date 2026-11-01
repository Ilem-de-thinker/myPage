<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformMetrics;
use App\Domains\Ai\Services\AiSettings;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(PlatformMetrics $metrics, AiSettings $ai): View
    {
        return view('admin.index', [
            'metrics' => $metrics->snapshot(),
            'ai' => [
                'enabled' => $ai->enabled(),
                'provider' => $ai->defaultProvider(),
                'model' => $ai->defaultModel(),
                'configured' => $ai->configured(),
            ],
        ]);
    }
}
