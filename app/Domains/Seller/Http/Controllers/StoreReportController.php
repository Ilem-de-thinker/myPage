<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Admin\Models\StoreReport;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreReportController extends Controller
{
    public function store(Request $request, StorePage $page): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:fraud,not_delivered,not_as_described,misleading_listing,payment_problem,other'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'reporter_name' => ['nullable', 'string', 'max:120'],
            'reporter_contact' => ['nullable', 'string', 'max:190'],
        ]);

        StoreReport::create([
            'store_page_id' => $page->getKey(),
            ...$data,
            'status' => 'open',
        ]);

        return back()->with('report_sent', 'Your report was sent to mypage.ng. We will review the store.');
    }
}
