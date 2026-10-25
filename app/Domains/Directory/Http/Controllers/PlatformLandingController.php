<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Catalog\Models\Review;
use App\Domains\Directory\Services\DirectoryCatalogQuery;
use App\Domains\Directory\Services\DiscoveryQuery;
use App\Shared\Support\PlatformNetwork;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformLandingController
{
    public function __invoke(Request $request, DiscoveryQuery $discovery, DirectoryCatalogQuery $catalog): View
    {
        return view('pages.landing', [
            'stores' => $discovery->proofStrip(6),
            'products' => $catalog->products(new Request, 6),
            'services' => $catalog->services(new Request, 6),
            'cities' => $discovery->busiestCities(6),
            'categories' => PlatformNetwork::categories(8),
            'reviews' => Review::query()->published()->where('is_featured', true)->whereHas('storePage', fn ($query) => $query->discoverable())->with('storePage')->latest('published_at')->limit(6)->get(),
        ]);
    }
}
