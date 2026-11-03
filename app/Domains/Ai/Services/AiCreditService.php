<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

/**
 * Enforces Mercho's monthly allowance and resolves the seller's actual plan.
 *
 * The ledger remains the source of usage truth so every code path that records a
 * successful provider call is counted here, regardless of which feature asked
 * for it.
 */
class AiCreditService
{
    public function __construct(
        private readonly SubscriptionAvailability $subscriptions,
        private readonly AiSettings $settings,
        private readonly AiPlanLimits $plans,
    ) {}

    public function assertAvailable(?StorePage $page, ?string $task = null): void
    {
        if ($page === null) {
            return;
        }

        if ($this->subscriptions->enabled()) {
            $remaining = $this->remaining($page);

            abort_if($remaining !== null && $remaining < 1, 429, 'This store has reached its monthly Mercho limit.');
        }

        if ($task === null) {
            return;
        }

        $limit = $this->settings->limitFor($task);

        if ($limit === null) {
            return;
        }

        $used = $this->featureUsed($page, $task);

        abort_if($used >= $limit, 429, 'This store has reached its monthly Mercho limit for '.AiSettings::taskLabel($task).'.');
    }

    /**
     * @return array{plan: string, limit: int|null, used: int, remaining: int|null, requested: int, allowed: int, task: string|null, feature_limit: int|null, feature_used: int, feature_remaining: int|null}
     */
    public function estimate(?StorePage $page, int $requested, ?string $task = null): array
    {
        if ($page === null) {
            return ['plan' => 'system', 'limit' => null, 'used' => 0, 'remaining' => null, 'requested' => $requested, 'allowed' => $requested, 'task' => $task, 'feature_limit' => null, 'feature_used' => 0, 'feature_remaining' => null];
        }

        $featureLimit = $task !== null ? $this->settings->limitFor($task) : null;
        $featureUsed = $task !== null ? $this->featureUsed($page, $task) : 0;
        $featureRemaining = $featureLimit !== null ? max(0, $featureLimit - $featureUsed) : null;

        if (! $this->subscriptions->enabled()) {
            $allowed = $featureRemaining === null ? $requested : min($requested, $featureRemaining);

            return ['plan' => 'platform', 'limit' => null, 'used' => 0, 'remaining' => null, 'requested' => $requested, 'allowed' => $allowed, 'task' => $task, 'feature_limit' => $featureLimit, 'feature_used' => $featureUsed, 'feature_remaining' => $featureRemaining];
        }

        // The plan row is authoritative. Config only kicks in for a seller with
        // no writable subscription, and its fallback is finite by design.
        $resolved = $this->plans->forPage($page);
        $plan = $resolved['slug'];
        $limit = $resolved['monthly_credits'];
        $used = AiUsageLedger::query()
            ->where('seller_id', $page->seller_id)
            ->whereIn('status', ['pending', 'used'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
        $remaining = $limit === null ? null : max(0, $limit - $used);
        $allowed = $remaining === null ? $requested : min($requested, $remaining);

        if ($featureRemaining !== null) {
            $allowed = min($allowed, $featureRemaining);
        }

        return ['plan' => $plan, 'limit' => $limit, 'used' => $used, 'remaining' => $remaining, 'requested' => $requested, 'allowed' => $allowed, 'task' => $task, 'feature_limit' => $featureLimit, 'feature_used' => $featureUsed, 'feature_remaining' => $featureRemaining];
    }

    public function remaining(?StorePage $page, ?string $task = null): ?int
    {
        $estimate = $this->estimate($page, 1, $task);

        return $estimate['remaining'];
    }

    private function featureUsed(StorePage $page, string $task): int
    {
        return AiUsageLedger::query()
            ->where('seller_id', $page->seller_id)
            ->where('task', $task)
            ->whereIn('status', ['pending', 'used'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }
}
