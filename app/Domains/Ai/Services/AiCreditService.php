<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

class AiCreditService
{
    public function __construct(
        private readonly SubscriptionAvailability $subscriptions,
        private readonly AiSettings $settings,
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

        // A plan with its own credit entry gets that allowance. Anything else
        // falls back to the paid bucket while writable and the free bucket when
        // it is not, so an unconfigured plan is never accidentally unlimited.
        $subscription = $page->seller?->subscriptions()->with('plan')->first();
        $plan = $subscription?->canWrite()
            ? ($subscription->plan?->slug ?? 'paid')
            : 'free';
        $limit = (int) (config("ai.plans.{$plan}.monthly_credits") ?? config('ai.plans.paid.monthly_credits', 0));
        $used = AiUsageLedger::query()->where('seller_id', $page->seller_id)->where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $remaining = $limit > 0 ? max(0, $limit - $used) : null;
        $allowed = $remaining === null ? $requested : min($requested, $remaining);

        if ($featureRemaining !== null) {
            $allowed = min($allowed, $featureRemaining);
        }

        return ['plan' => $plan, 'limit' => $limit > 0 ? $limit : null, 'used' => $used, 'remaining' => $remaining, 'requested' => $requested, 'allowed' => $allowed, 'task' => $task, 'feature_limit' => $featureLimit, 'feature_used' => $featureUsed, 'feature_remaining' => $featureRemaining];
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
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();
    }
}
