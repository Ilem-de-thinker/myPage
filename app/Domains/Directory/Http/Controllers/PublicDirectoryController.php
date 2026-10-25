<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Catalog\Models\Category;
use App\Domains\Directory\Services\DirectoryCatalogQuery;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class PublicDirectoryController extends Controller
{
    public function stores(Request $request, DirectoryCatalogQuery $catalog): View
    {
        return view('pages.directory.catalog', [
            'kind' => 'stores',
            'heading' => 'Find a store or studio worth visiting',
            'items' => $catalog->stores($request),
            'states' => $this->states(),
            'cities' => $this->cities(),
            'categories' => collect(),
        ]);
    }

    public function products(Request $request, DirectoryCatalogQuery $catalog): View
    {
        return view('pages.directory.catalog', [
            'kind' => 'products',
            'heading' => 'Products from independent Nigerian stores',
            'items' => $catalog->products($request),
            'states' => $this->states(),
            'cities' => $this->cities(),
            'categories' => $this->categories(),
        ]);
    }

    public function services(Request $request, DirectoryCatalogQuery $catalog): View
    {
        return view('pages.directory.catalog', [
            'kind' => 'services',
            'heading' => 'Services and studios you can book directly',
            'items' => $catalog->services($request),
            'states' => $this->states(),
            'cities' => $this->cities(),
            'categories' => collect(),
        ]);
    }

    public function store(StorePage $storePage): View
    {
        abort_unless($storePage->isDiscoverable(), 404);
        $storePage->load([
            'products' => fn ($query) => $query->active()->where('is_directory_listed', true)->with('images')->latest()->limit(6),
            'services' => fn ($query) => $query->where('is_active', true)->where('is_directory_listed', true)->latest()->limit(6),
        ]);

        return view('pages.directory.store', ['page' => $storePage]);
    }

    public function category(Request $request, string $category, DirectoryCatalogQuery $catalog): View
    {
        $request->merge(['category' => $category]);

        return view('pages.directory.catalog', [
            'kind' => 'products',
            'heading' => 'Explore '.$category,
            'items' => $catalog->products($request),
            'states' => $this->states(),
            'cities' => $this->cities(),
            'categories' => $this->categories(),
        ]);
    }

    private function states(): Collection
    {
        return StorePage::query()->discoverable()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state');
    }

    private function cities(): Collection
    {
        return StorePage::query()->discoverable()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
    }

    private function categories(): Collection
    {
        return Category::query()
            ->whereHas('products', fn ($query) => $query
                ->active()
                ->where('is_directory_listed', true)
                ->whereHas('storePage', fn ($store) => $store->discoverable()))
            ->orderBy('name')
            ->get(['name', 'slug']);
    }
}
