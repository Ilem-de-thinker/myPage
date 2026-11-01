<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Directory\Services\DirectoryCatalogQuery;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use App\Shared\Support\SearchQueryParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class MarketplaceAssistantController extends Controller
{
    public function __invoke(Request $request, AiManager $ai, DirectoryCatalogQuery $catalog, SearchQueryParser $parser): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1200'],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:1200'],
        ]);

        $visitorKey = 'marketplace-assistant:'.hash('sha256', $request->ip().'|'.config('app.key')).':'.now()->format('Y-m-d');
        $usage = (int) Cache::get($visitorKey, 0);
        if ($usage >= (int) config('platform.directory.assistant_daily_limit', 20)) {
            return response()->json(['message' => 'You have reached today’s free guided-search limit. You can keep browsing the directory normally.'], 429);
        }
        Cache::put($visitorKey, $usage + 1, now()->endOfDay());

        $message = trim($data['message']);
        $history = collect($data['history'] ?? [])->map(fn (array $entry): string => strtoupper($entry['role']).': '.$entry['content'])->implode("\n");
        $aiMessage = $history === '' ? $message : "Earlier conversation:\n{$history}\n\nCurrent request:\n{$message}";
        $intent = [
            'intent' => $message,
            'kind' => 'any',
            'category' => '',
            'budget_min' => '',
            'budget_max' => '',
            'location' => '',
            'urgency' => '',
            'preferences' => [],
            'questions' => [],
        ];

        $intentCacheKey = 'marketplace-intent:'.hash('sha256', mb_strtolower($message));
        $cachedIntent = Cache::get($intentCacheKey);
        $aiRan = false;
        if (is_array($cachedIntent)) {
            $intent = array_merge($intent, $cachedIntent);
            $aiRan = true;
        } else {
            try {
                $result = $ai->run('marketplace_intent', ['message' => $aiMessage]);
                if (is_array($result)) {
                    $intent = array_merge($intent, $result);
                    $aiRan = true;
                    Cache::put($intentCacheKey, $result, now()->addMinutes(15));
                }
            } catch (Throwable) {
                // Plain search remains available if an AI provider is unavailable.
            }
        }

        $kind = in_array($intent['kind'], ['product', 'service'], true) ? $intent['kind'] : 'any';

        /*
            The deterministic parse of the same message. Every facet falls back to
            it independently: the AI wins where it produced a value, the parser
            covers what the provider missed — and when the provider is down, it is
            what keeps location, budget and a real search term working at all.
        */
        $places = $this->places();
        $parsed = $parser->parse($message, $places);

        $searchTerm = filled($intent['search_term'] ?? null) ? (string) $intent['search_term'] : '';
        if ($searchTerm === '' && ! $aiRan) {
            $searchTerm = $parsed['search_term'];
        }
        if ($searchTerm === '' && filled($intent['category'] ?? null)) {
            $searchTerm = (string) $intent['category'];
        }

        $location = filled($intent['location'] ?? null) ? trim((string) $intent['location']) : $parsed['location'];
        $place = filled($location) ? ($parser->place($location, $places) ?? []) : [];

        $budgetMin = $parser->amount($intent['budget_min'] ?? null) ?? $parsed['budget_min'];
        $budgetMax = $parser->amount($intent['budget_max'] ?? null) ?? $parsed['budget_max'];
        if ($budgetMin !== null && $budgetMax !== null && $budgetMin > $budgetMax) {
            [$budgetMin, $budgetMax] = [$budgetMax, $budgetMin];
        }

        $filters = [
            'q' => $searchTerm,
            'state' => $place['state'] ?? null,
            'city' => $place['city'] ?? null,
            'min_price' => $budgetMin,
            'max_price' => $budgetMax,
        ];
        $query = Request::create('/', 'GET', array_filter($filters, fn ($value): bool => filled($value)));
        $products = $kind !== 'service' ? $catalog->products($query, 6)->getCollection() : collect();
        $services = $kind !== 'product' ? $catalog->services($query, 6)->getCollection() : collect();
        $candidates = $products->map(fn ($item): array => $this->result($item, 'product', $message))->merge($services->map(fn ($item): array => $this->result($item, 'service', $message)))->take(12)->values();
        $rankCacheKey = 'marketplace-rank:'.hash('sha256', json_encode([$message, $candidates->pluck('id')->all()]));
        $ranked = Cache::get($rankCacheKey);
        if (! is_array($ranked) && $candidates->isNotEmpty()) {
            try {
                $ranked = $ai->run('marketplace_rank', ['message' => $aiMessage, 'candidates' => $candidates->all()]);
                if (is_array($ranked)) {
                    Cache::put($rankCacheKey, $ranked, now()->addMinutes(10));
                }
            } catch (Throwable) {
                $ranked = null;
            }
        }
        try {
            if (is_array($ranked['results'] ?? null)) {
                $byId = $candidates->keyBy('id');
                $ordered = collect($ranked['results'])->map(function (array $row) use ($byId): ?array {
                    $candidate = $byId->get($row['id']);
                    if (! $candidate) {
                        return null;
                    }

                    $candidate['reason'] = $row['reason'];

                    return $candidate;
                })->filter()->values();
                $candidates = $ordered->merge($candidates->reject(fn (array $candidate): bool => $ordered->contains('id', $candidate['id'])))->values();
            }
        } catch (Throwable) {
            // Database-backed matches remain useful when ranking is unavailable.
        }

        return response()->json([
            'intent' => $intent,
            'applied' => array_filter([
                'q' => filled($searchTerm) ? (string) $searchTerm : null,
                'location' => filled($location) ? $location : null,
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'kind' => $kind !== 'any' ? $kind : null,
            ], fn ($value): bool => filled($value)),
            'questions' => array_slice($intent['questions'] ?? [], 0, 2),
            'results' => $candidates,
            'disclosure' => 'AI helps narrow the directory. Store details and prices come from the merchant’s published page.',
        ]);
    }

    private function result(object $item, string $type, string $query): array
    {
        $page = $item->storePage;
        $name = $type === 'product' ? $item->name : $item->name;
        $price = $type === 'product' ? $item->priceLabel : $item->priceWithUnitLabel();

        return [
            'id' => $type.':'.$item->id,
            'type' => $type,
            'name' => $name,
            'price' => $price,
            'originalPrice' => $item->isOnSale() ? Money::format($item->originalPriceKobo()) : null,
            'store' => $page->name,
            'location' => $page->locationLabel() ?: 'Nigeria',
            'verified' => (bool) $page->verified_at,
            'priceValue' => $item->effectivePriceKobo(),
            'originalPriceValue' => $item->originalPriceKobo(),
            'url' => route('directory.go', ['type' => $type, 'id' => $item->id, 'source' => 'help_me_buy', 'q' => $query]),
            'whatsappUrl' => route('directory.go', ['type' => 'whatsapp', 'id' => $page->id, 'source' => 'help_me_buy', 'q' => $query]),
            'reason' => $type === 'product' ? 'Published product match' : 'Published service match',
        ];
    }

    /**
     * The places the directory can actually filter by, resolved once so a
     * buyer's "Lekki" lands on the stored casing of a real city row instead of
     * on a state filter that could never match.
     *
     * @return array{states: list<string>, cities: list<string>}
     */
    private function places(): array
    {
        return Cache::remember('directory:place-names', now()->addMinutes(30), function (): array {
            $discoverable = StorePage::query()->discoverable();

            return [
                'states' => (clone $discoverable)->whereNotNull('state')->distinct()->pluck('state')->all(),
                'cities' => (clone $discoverable)->whereNotNull('city')->distinct()->pluck('city')->all(),
            ];
        });
    }
}
