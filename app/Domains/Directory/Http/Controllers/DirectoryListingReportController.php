<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Admin\Models\StoreReport;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class DirectoryListingReportController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:fraud,not_as_described,misleading_listing,other'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'reporter_name' => ['nullable', 'string', 'max:120'],
            'reporter_contact' => ['nullable', 'string', 'max:190'],
        ]);

        $model = match ($type) {
            'store' => StorePage::query()->findOrFail($id),
            'product' => Product::query()->with('storePage')->where('is_directory_listed', true)->findOrFail($id),
            'service' => Service::query()->with('storePage')->where('is_directory_listed', true)->findOrFail($id),
            default => abort(404),
        };

        $storePage = $type === 'store' ? $model : $model->storePage;

        abort_unless($storePage?->isDiscoverable(), 404);

        StoreReport::create([
            'store_page_id' => $storePage->getKey(),
            'product_id' => $type === 'product' ? $model->getKey() : null,
            'service_id' => $type === 'service' ? $model->getKey() : null,
            ...$data,
            'status' => 'open',
        ]);

        return back()->with('report_sent', 'Your listing report was sent to mypage.ng for review.');
    }
}
