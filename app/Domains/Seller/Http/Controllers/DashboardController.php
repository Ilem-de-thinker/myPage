<?php

namespace App\Domains\Seller\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $page = $request->user()->storePages()->with('sections')->first();

        return view('dashboard.index', ['page' => $page]);
    }
}
