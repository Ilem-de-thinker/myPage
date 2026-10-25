<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Directory\Services\DirectoryCatalogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class MarketplaceAssistantController extends Controller
{
    public function __invoke(Request $request, AiManager $ai, DirectoryCatalogQuery $catalog): JsonResponse
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
        if (is_array($cachedIntent)) {
            $intent = array_merge($intent, $cachedIntent);
        } else {
            try {
                $result = $ai->run('marketplace_intent', ['message' => $aiMessage]);
                if (is_array($result)) {
                    $intent = array_merge($intent, $result);
                    Cache::put($intentCacheKey, $result, now()->addMinutes(15));
                }
            } catch (Throwable) {
                // Plain search remains available if an AI provider is unavailable.
            }
        }

        $kind = in_array($intent['kind'], ['product', 'service'], true) ? $intent['kind'] : 'any';
        $filters = [
            'q' => filled($intent['category'] ?? null) ? (string) $intent['category'] : $message,
            'state' => $this->locationPart((string) $intent['location']),
            'min_price' => $this->amount($intent['budget_min']),
            'max_price' => $this->amount($intent['budget_max']),
        ];
        $query = Request::create('/', 'GET', array_filter($filters, fn ($value): bool => filled($value)));
        $products = $kind !== 'service' ? $catalog->products($query, 6)->getCollection() : collect();
        $services = $kind !== 'product' ? $catalog->services($query, 6)->getCollection() : collect();
        $candidates = $products->map(fn ($item): array => $this->result($item, 'product', $message))->merge($services->map(fn ($item): array => $this->result($item, 'service', $message)))->take(12)->values();
        $rankCacheKey = 'marketplace-rank:'.hash('sha256', json_encode([$message, $candidates->pluck('id')->all()]));
        $ranked = Cache::get($rankCacheKey);
        if (! is_array($ranked)) {
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
            'questions' => array_slice($intent['questions'] ?? [], 0, 2),
            'results' => $candidates,
            'disclosure' => 'AI helps narrow the directory. Store details and prices come from the merchant’s published page.',
        ]);
    }

    private function result(object $item, string $type, string $query): array
    {
        $page = $item->storePage;
        $name = $type === 'product' ? $item->name : $item->name;
        $price = $type === 'product' ? $item->priceLabel : $item->priceLabel();

        return [
            'id' => $type.':'.$item->id,
            'type' => $type,
            'name' => $name,
            'price' => $price,
            'store' => $page->name,
            'location' => $page->locationLabel() ?: 'Nigeria',
            'verified' => (bool) $page->verified_at,
            'priceValue' => $item->price_kobo,
            'url' => route('directory.go', ['type' => $type, 'id' => $item->id, 'source' => 'help_me_buy', 'q' => $query]),
            'whatsappUrl' => route('directory.go', ['type' => 'whatsapp', 'id' => $page->id, 'source' => 'help_me_buy', 'q' => $query]),
            'reason' => $type === 'product' ? 'Published product match' : 'Published service match',
        ];
    }

    private function amount(mixed $value): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return filled($digits) ? (int) $digits : null;
    }

    private function locationPart(string $location): ?string
    {
        return filled($location) ? trim(explode(',', $location)[0]) : null;
    }
}
