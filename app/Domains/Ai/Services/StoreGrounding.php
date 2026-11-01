<?php

namespace App\Domains\Ai\Services;

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;

class StoreGrounding
{
    /**
     * @return array<string, mixed>
     */
    public function forPage(StorePage $page): array
    {
        $products = Product::query()
            ->where('store_page_id', $page->id)
            ->active()
            ->with('category:id,name')
            ->orderByDesc('is_featured')
            ->limit(80)
            ->get(['id', 'store_page_id', 'category_id', 'name', 'description', 'price_kobo', 'compare_at_kobo', 'sale_starts_at', 'sale_ends_at', 'track_stock', 'stock_qty'])
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'description' => $product->description,
                'category' => $product->category?->name,
                'price_kobo' => $product->effectivePriceKobo(),
                'original_price_kobo' => $product->originalPriceKobo(),
                'sale_ends_at' => $product->saleEndsAt()?->toIso8601String(),
                'in_stock' => ! $product->isSoldOut(),
            ])->values()->all();

        $services = Service::query()
            ->where('store_page_id', $page->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->limit(80)
            ->get(['name', 'description', 'price_kobo', 'compare_at_kobo', 'sale_starts_at', 'sale_ends_at', 'duration_min', 'turnaround_label'])
            ->map(fn (Service $service): array => [
                'name' => $service->name,
                'description' => $service->description,
                'price_kobo' => $service->effectivePriceKobo(),
                'original_price_kobo' => $service->originalPriceKobo(),
                'sale_ends_at' => $service->saleEndsAt()?->toIso8601String(),
                'duration_min' => $service->duration_min,
                'turnaround' => $service->turnaround_label,
            ])->values()->all();

        $faq = $page->block('home', 'faq')?->content ?? [];

        return [
            'merchant' => ['name' => $page->name, 'tagline' => $page->tagline, 'bio' => $page->bio, 'city' => $page->city, 'state' => $page->state, 'address' => $page->address, 'public_email' => $page->public_email],
            'products' => $products,
            'services' => $services,
            'faq' => $faq,
            'whatsapp_available' => filled($page->whatsapp_number),
        ];
    }
}
