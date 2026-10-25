<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

class AiCreditService
{
    public function __construct(private readonly SubscriptionAvailability $subscriptions) {}

    public function assertAvailable(?StorePage $page): void
    {
        if ($page === null || ! $this->subscriptions->enabled()) {
            return;
        }

        $remaining = $this->remaining($page);

        abort_if($remaining !== null && $remaining < 1, 429, 'This store has reached its monthly AI limit.');
    }

    /**
     * @return array{plan: string, limit: int|null, used: int, remaining: int|null, requested: int, allowed: int}
     */
    public function estimate(?StorePage $page, int $requested): array
    {
        if ($page === null) {
            return ['plan' => 'system', 'limit' => null, 'used' => 0, 'remaining' => null, 'requested' => $requested, 'allowed' => $requested];
        }

        if (! $this->subscriptions->enabled()) {
            return ['plan' => 'platform', 'limit' => null, 'used' => 0, 'remaining' => null, 'requested' => $requested, 'allowed' => $requested];
        }

        $plan = $page->seller?->subscriptions()->with('plan')->latest()->first()?->canWrite() ? 'paid' : 'free';
        $limit = (int) config("ai.plans.{$plan}.monthly_credits", 0);
        $used = AiUsageLedger::query()->where('seller_id', $page->seller_id)->where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $remaining = $limit > 0 ? max(0, $limit - $used) : null;

        return ['plan' => $plan, 'limit' => $limit > 0 ? $limit : null, 'used' => $used, 'remaining' => $remaining, 'requested' => $requested, 'allowed' => $remaining === null ? $requested : min($requested, $remaining)];
    }

    public function remaining(?StorePage $page): ?int
    {
        $estimate = $this->estimate($page, 1);

        return $estimate['remaining'];
    }
}
