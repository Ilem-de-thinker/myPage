<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Jobs\ProcessProductImport;
use App\Domains\Catalog\Models\ProductImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductImportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $path = $data['file']->store('imports', 'local');
        $import = ProductImport::create(['seller_id' => $request->user()->getKey(), 'store_page_id' => $page->getKey(), 'filename' => $data['file']->getClientOriginalName(), 'path' => $path]);
        ProcessProductImport::dispatch($import->getKey());

        return back()->with('status', 'Product import queued. Your products will appear after the queue finishes.');
    }
}
