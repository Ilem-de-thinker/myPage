<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Services\AiSettings;
use App\Domains\Ai\Services\BuyerAiBudget;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use App\Shared\Support\SearchQueryParser;
use App\Shared\Support\StoreUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/*
    The in-store AI search behind the header's search icon.

    One buyer, one shop: the query is understood with the same marketplace
    intent task the directory assistant uses, then answered against this
    store's own published products and services — never a sibling's. When the
    AI provider is unavailable the endpoint degrades to a keyword search over
    the same catalogue instead of failing, which is what `mode` reports to the
    overlay so it can say so.
*/
final class StoreSearchController extends Controller
{
    public function __construct(
        private readonly AiSettings $settings,
        private readonly BuyerAiBudget $budget,
    ) {}

    public function __invoke(Request $request, StorePage $page, AiManager $ai, SearchQueryParser $parser): JsonResponse
    {
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is not enabled for this store.');

        $this->budget->assertAvailable($page);

        $data = $request->validate(['message' => ['required', 'string', 'max:400']]);
        $message = trim($data['message']);

        $intentCacheKey = 'store-search:'.hash('sha256', $page->getKey().'|'.mb_strtolower($message));
        $cachedIntent = Cache::get($intentCacheKey);
        $mode = 'keyword';
        $intent = [];

        if (is_array($cachedIntent)) {
            $intent = $cachedIntent;
            $mode = 'ai';
        } else {
            try {
                $result = $ai->runPlatform('marketplace_intent', ['message' => $message, 'store' => $page->name], $page);
                if (is_array($result)) {
                    $intent = $result;
                    $mode = 'ai';
                    Cache::put($intentCacheKey, $result, now()->addMinutes(15));
                }
            } catch (Throwable) {
                // Keyword search over this store's catalogue still works.
            }
        }

        $parsed = $parser->parse($message);
        $searchTerm = filled($intent['search_term'] ?? null) ? (string) $intent['search_term'] : ($parsed['search_term'] !== '' ? $parsed['search_term'] : $message);
        $budgetMin = $parser->amount($intent['budget_min'] ?? null) ?? $parsed['budget_min'];
        $budgetMax = $parser->amount($intent['budget_max'] ?? null) ?? $parsed['budget_max'];
        if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) {
            [$budgetMin, $budgetMax] = [$budgetMax, $budgetMin];
        }

        $kind = in_array($intent['kind'] ?? null, ['product', 'service'], true) ? $intent['kind'] : 'any';
        $limit = fn ($query) => $query
            ->when($budgetMin !== null, fn ($q) => $q->where('price_kobo', '>=', $budgetMin))
            ->when($budgetMax !== null, fn ($q) => $q->where('price_kobo', '<=', $budgetMax))
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get();

        $products = $kind === 'service'
            ? collect()
            : $limit(Product::query()->select('products.*')->where('store_page_id', $page->id)->active()->search($searchTerm));
        $services = $kind === 'product'
            ? collect()
            : $limit(Service::query()->select('services.*')->where('store_page_id', $page->id)->where('is_active', true)->where('is_directory_listed', true)->search($searchTerm));

        $results = $products->map(fn (Product $product): array => $this->result($product, 'product', $page))
            ->merge($services->map(fn (Service $service): array => $this->result($service, 'service', $page)))
            ->take(12)
            ->values();

        return response()->json([
            'mode' => $mode,
            'applied' => array_filter([
                'q' => $searchTerm,
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'kind' => $kind === 'any' ? null : $kind,
            ], fn ($value): bool => filled($value)),
            'questions' => array_slice($intent['questions'] ?? [], 0, 2),
            'results' => $results,
            'disclosure' => 'AI helps read your query. Product details and prices come from this store’s published page.',
        ]);
    }

    /** @return array<string, mixed> */
    private function result(Product|Service $item, string $type, StorePage $page): array
    {
        return [
            'type' => $type,
            'name' => $item->name,
            'description' => Str::limit(strip_tags((string) $item->description), 140),
            'price' => $type === 'product' ? $item->priceLabel : $item->priceWithUnitLabel(),
            'original_price' => $item->isOnSale() ? Money::format($item->originalPriceKobo()) : null,
            'url' => StoreUrl::path($type === 'product' ? 'storefront.product' : 'storefront.service', [
                'slug' => $page->slug,
                $type === 'product' ? 'productSlug' : 'serviceSlug' => $item->slug,
            ]),
        ];
    }
}
