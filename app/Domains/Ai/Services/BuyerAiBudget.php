<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiRun;
use App\Domains\Seller\Models\StorePage;

/**
 * Caps buyer-facing Mercho calls per store, per day.
 *
 * Shoppers trigger these calls, so they are funded by the platform rather than
 * the merchant's monthly allowance. An uncapped free endpoint would be a cheap
 * way to burn the provider budget, so every store gets a daily budget that
 * grows with its plan. The count comes from provider runs, which also include
 * failed attempts, because those still cost money.
 */
final class BuyerAiBudget
{
    /** @var list<string> */
    public const TASKS = ['store_assistant', 'service_booking_assistant', 'marketplace_intent'];

    public function __construct(private readonly AiPlanLimits $plans) {}

    public function assertAvailable(StorePage $page): void
    {
        abort_unless((bool) config('ai.buyer_assistant.enabled', true), 503, 'The Mercho assistant is unavailable right now.');

        $cap = $this->dailyCap($page);

        if ($cap < 1) {
            abort(429, 'The Mercho assistant is unavailable for this store.');
        }

        abort_if(
            $this->usedToday($page) >= $cap,
            429,
            'The Mercho assistant for this store has reached its daily limit. Please try again tomorrow.',
        );
    }

    public function dailyCap(StorePage $page): int
    {
        $slug = $this->plans->forPage($page)['slug'];
        $caps = (array) config('ai.buyer_assistant.daily_caps', []);
        $cap = $caps[$slug] ?? config('ai.buyer_assistant.fallback_daily_cap', 25);

        return max(0, (int) $cap);
    }

    public function usedToday(StorePage $page): int
    {
        return AiRun::query()
            ->where('store_page_id', $page->getKey())
            ->whereIn('task', self::TASKS)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }
}
