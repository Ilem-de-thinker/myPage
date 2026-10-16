<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformMetrics;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(PlatformMetrics $metrics): View
    {
        return view('admin.index', [
            'metrics' => $metrics->snapshot(),
        ]);
    }
}
