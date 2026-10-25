<?php

namespace App\Domains\Directory\Services;

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class DirectoryCatalogQuery
{
    public function stores(Request $request, int $perPage = 18): LengthAwarePaginator
    {
        $query = StorePage::query()->discoverable();
        $coordinates = $this->coordinates($request);

        if ($coordinates !== null && $request->input('sort') === 'nearest') {
            [$latitude, $longitude] = $coordinates;
            $query->select('store_pages.*')->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) as distance_km',
                [$latitude, $longitude, $latitude]
            )->orderBy('distance_km');
        }

        if ($term = trim($request->string('q')->toString())) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('tagline', 'like', $like)
                ->orWhere('bio', 'like', $like));
        }

        return $query
            ->when($request->filled('state'), fn (Builder $query) => $query->where('state', $request->string('state')))
            ->when($request->filled('city'), fn (Builder $query) => $query->where('city', $request->string('city')))
            ->when($request->boolean('verified'), fn (Builder $query) => $query->whereNotNull('verified_at'))
            ->when($request->input('sort') === 'newest', fn (Builder $query) => $query->latest('store_pages.created_at'))
            ->orderByDesc('is_featured')
            ->orderByDesc('verified_at')
            ->orderByDesc('rating')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function products(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        return $this->productBase($request)
            ->with(['storePage', 'images', 'category'])
            ->orderByDesc('products.is_featured')
            ->orderByDesc('products.view_count')
            ->orderBy('products.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function services(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        return $this->serviceBase($request)
            ->with(['storePage'])
            ->orderByDesc('services.is_featured')
            ->orderByDesc('services.view_count')
            ->orderBy('services.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Builder<Product> */
    public function productBase(Request $request): Builder
    {
        $query = Product::query()
            ->select('products.*')
            ->active()
            ->where('is_directory_listed', true)
            ->whereHas('storePage', fn (Builder $query) => $query->discoverable())
            ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $request->string('category'))))
            ->when($request->filled('state'), fn (Builder $query) => $query->whereHas('storePage', fn (Builder $store) => $store->where('state', $request->string('state'))))
            ->when($request->filled('city'), fn (Builder $query) => $query->whereHas('storePage', fn (Builder $store) => $store->where('city', $request->string('city'))))
            ->when($request->boolean('verified'), fn (Builder $query) => $query->whereHas('storePage', fn (Builder $store) => $store->whereNotNull('verified_at')))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->where('price_kobo', '>=', (int) $request->input('min_price') * 100))
            ->when($request->filled('max_price'), fn (Builder $query) => $query->where('price_kobo', '<=', (int) $request->input('max_price') * 100))
            ->when($request->input('sort') === 'price_low', fn (Builder $query) => $query->orderBy('price_kobo'))
            ->when($request->input('sort') === 'price_high', fn (Builder $query) => $query->orderByDesc('price_kobo'))
            ->when($request->input('sort') === 'newest', fn (Builder $query) => $query->latest('products.created_at'));

        $coordinates = $this->coordinates($request);
        if ($coordinates !== null && $request->input('sort') === 'nearest') {
            [$latitude, $longitude] = $coordinates;
            $query->join('store_pages', 'store_pages.id', '=', 'products.store_page_id')
                ->select('products.*')
                ->selectRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(store_pages.latitude)) * cos(radians(store_pages.longitude) - radians(?)) + sin(radians(?)) * sin(radians(store_pages.latitude)))) as distance_km',
                    [$latitude, $longitude, $latitude]
                )->orderBy('distance_km');
        }

        return $query;
    }

    /** @return Builder<Service> */
    public function serviceBase(Request $request): Builder
    {
        $query = Service::query()
            ->select('services.*')
            ->where('is_active', true)
            ->where('is_directory_listed', true)
            ->whereHas('storePage', fn (Builder $query) => $query->discoverable())
            ->when($request->filled('q'), fn (Builder $query) => $query->search($request->string('q')->toString()))
            ->when($request->filled('state'), fn (Builder $query) => $query->whereHas('storePage', fn (Builder $store) => $store->where('state', $request->string('state'))))
            ->when($request->filled('city'), fn (Builder $query) => $query->whereHas('storePage', fn (Builder $store) => $store->where('city', $request->string('city'))))
            ->when($request->boolean('verified'), fn (Builder $query) => $query->whereHas('storePage', fn (Builder $store) => $store->whereNotNull('verified_at')))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->where('price_kobo', '>=', (int) $request->input('min_price') * 100))
            ->when($request->filled('max_price'), fn (Builder $query) => $query->where('price_kobo', '<=', (int) $request->input('max_price') * 100))
            ->when($request->input('sort') === 'price_low', fn (Builder $query) => $query->orderBy('price_kobo'))
            ->when($request->input('sort') === 'price_high', fn (Builder $query) => $query->orderByDesc('price_kobo'))
            ->when($request->input('sort') === 'newest', fn (Builder $query) => $query->latest('services.created_at'));

        $coordinates = $this->coordinates($request);
        if ($coordinates !== null && $request->input('sort') === 'nearest') {
            [$latitude, $longitude] = $coordinates;
            $query->join('store_pages', 'store_pages.id', '=', 'services.store_page_id')
                ->select('services.*')
                ->selectRaw(
                    '(6371 * acos(cos(radians(?)) * cos(radians(store_pages.latitude)) * cos(radians(store_pages.longitude) - radians(?)) + sin(radians(?)) * sin(radians(store_pages.latitude)))) as distance_km',
                    [$latitude, $longitude, $latitude]
                )->orderBy('distance_km');
        }

        return $query;
    }

    /** @return array{0: float, 1: float}|null */
    private function coordinates(Request $request): ?array
    {
        if (! is_numeric($request->input('lat')) || ! is_numeric($request->input('lng'))) {
            return null;
        }

        $latitude = (float) $request->input('lat');
        $longitude = (float) $request->input('lng');

        return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180
            ? [$latitude, $longitude]
            : null;
    }
}
