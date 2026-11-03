<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Carbon;

/**
 * Builds the merchant-facing view of Mercho usage.
 *
 * The launcher needs a live count and the billing page needs the same answer in
 * server-rendered HTML, so the calculation lives here instead of in either
 * template. The calendar month is intentional: it is the period the ledger has
 * always used, and changing it to a subscription anniversary would silently
 * reset or extend a merchant's allowance.
 */
final class AiUsageSummary
{
    public function __construct(
        private readonly SubscriptionAvailability $subscriptions,
        private readonly AiPlanLimits $plans,
        private readonly AiLimits $limits,
    ) {}

    /**
     * @return array{plan: string, billing_enabled: bool, period_start: string, period_end: string, limit: int|null, used: int, remaining: int|null, percent: int|null, unlimited: bool, image_limit: int, state: string, tasks: list<array{task: string, label: string, used: int}>}|null
     */
    public function forPage(?StorePage $page): ?array
    {
        if ($page === null) {
            return null;
        }

        $billingEnabled = $this->subscriptions->enabled();
        $resolved = $this->plans->forPage($page);
        $start = Carbon::now()->startOfMonth();
        $end = $start->copy()->addMonth();

        $used = AiUsageLedger::query()
            ->where('seller_id', $page->seller_id)
            ->whereIn('status', ['pending', 'used'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();

        $limit = $billingEnabled ? $resolved['monthly_credits'] : null;
        $unlimited = $limit === null;
        $remaining = $unlimited ? null : max(0, $limit - $used);
        $percent = $unlimited
            ? null
            : min(100, (int) round($used / max(1, $limit) * 100));
        $state = match (true) {
            $unlimited => 'unlimited',
            $used >= $limit => 'exhausted',
            $percent >= 80 => 'warning',
            default => 'normal',
        };

        return [
            'plan' => $billingEnabled ? $resolved['slug'] : 'platform',
            'billing_enabled' => $billingEnabled,
            'period_start' => $start->toIso8601String(),
            'period_end' => $end->toIso8601String(),
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'percent' => $percent,
            'unlimited' => $unlimited,
            'image_limit' => $this->limits->imageLimit($page),
            'state' => $state,
            'tasks' => $this->taskBreakdown($page, $start, $end),
        ];
    }

    /**
     * @return list<array{task: string, label: string, used: int}>
     */
    private function taskBreakdown(StorePage $page, Carbon $start, Carbon $end): array
    {
        return AiUsageLedger::query()
            ->where('seller_id', $page->seller_id)
            ->whereIn('status', ['pending', 'used'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('task, COUNT(*) as used')
            ->groupBy('task')
            ->orderByDesc('used')
            ->get()
            ->map(fn (AiUsageLedger $row): array => [
                'task' => $row->task,
                'label' => AiSettings::taskLabel($row->task),
                'used' => (int) $row->used,
            ])
            ->values()
            ->all();
    }
}
