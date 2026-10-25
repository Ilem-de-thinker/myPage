<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Analytics\Services\RecordStoreEvent;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;
use App\Shared\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class DirectoryRedirectController extends Controller
{
    public function __invoke(Request $request, string $type, int $id, RecordStoreEvent $events): RedirectResponse
    {
        $product = null;
        $service = null;

        abort_unless(in_array($type, ['store', 'product', 'service', 'whatsapp'], true), 404);

        if ($type === 'store') {
            $page = StorePage::query()->discoverable()->findOrFail($id);
            $url = StoreUrl::home($page);
        } elseif ($type === 'product') {
            $product = Product::query()->with('storePage')->find($id);
            $page = $product?->storePage;
            if (! $product || ! $page?->isDiscoverable()) {
                return redirect()->route('directory.index');
            }
            $url = $product->status === 'active' && $product->is_directory_listed
                ? StoreUrl::absolute($page, 'storefront.product', ['productSlug' => $product->slug])
                : StoreUrl::home($page);
        } elseif ($type === 'service') {
            $service = Service::query()->with('storePage')->find($id);
            $page = $service?->storePage;
            if (! $service || ! $page?->isDiscoverable()) {
                return redirect()->route('directory.index');
            }
            $url = $service->is_active && $service->is_directory_listed
                ? StoreUrl::absolute($page, 'storefront.service', ['serviceSlug' => $service->slug])
                : StoreUrl::home($page);
        } else {
            $page = StorePage::query()->discoverable()->findOrFail($id);
            $url = WhatsApp::link($page, 'Hello '.$page->name.', I found you on mypage.ng.');
            abort_unless($url !== null, 404);
        }

        $events->handle($page, 'directory_click', $request, [
            'product_id' => $type === 'product' ? $product->id : null,
            'service_id' => $type === 'service' ? $service->id : null,
            'directory_type' => $type,
            'directory_source' => $request->string('source', 'directory')->limit(120)->toString(),
            'query' => $request->string('q')->limit(120)->toString(),
            'category' => $request->string('category')->limit(80)->toString(),
        ]);

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').'utm_source=mypage_ng&utm_medium=directory&utm_campaign='.$type);
    }
}
