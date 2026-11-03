<?php

namespace App\Domains\Ai\Services;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Log;

/**
 * Resolves which Mercho allowances apply to a seller.
 *
 * Billing and AI used to read different sources, so a paid plan could silently
 * inherit the generic "paid" bucket. This class makes the plan row the source of
 * truth and keeps a deliberately conservative config fallback for installs that
 * have not been migrated or configured yet. A missing allowance must never be
 * read as unlimited by accident.
 */
final class AiPlanLimits
{
    /**
     * @return array{slug: string, monthly_credits: int|null, max_images_per_request: int, source: string}
     */
    public function forPage(StorePage $page): array
    {
        $subscription = $page->seller?->subscriptions()->with('plan')->first();
        $plan = $subscription?->plan;
        $writable = $subscription?->canWrite() ?? false;

        if ($plan !== null && $writable) {
            return [
                'slug' => $plan->slug,
                'monthly_credits' => $plan->ai_monthly_credits,
                'max_images_per_request' => $this->planImageLimit($plan->slug, $plan->ai_max_images_per_request),
                'source' => 'plan',
            ];
        }

        if ($plan === null && $writable) {
            Log::warning('AI plan row is missing for a writable subscription; using the paid fallback.', [
                'seller_id' => $page->seller_id,
            ]);
        }

        return $this->configFallback($writable ? 'paid' : 'free');
    }

    /**
     * The monthly credit allowance, or null when the plan is explicitly unlimited.
     */
    public function monthlyCredits(StorePage $page): ?int
    {
        return $this->forPage($page)['monthly_credits'];
    }

    /**
     * The plan's per-request image allowance before the absolute safety ceiling.
     */
    public function imageLimit(StorePage $page): int
    {
        return $this->forPage($page)['max_images_per_request'];
    }

    /**
     * @return array{slug: string, monthly_credits: int|null, max_images_per_request: int, source: string}
     */
    private function configFallback(string $slug): array
    {
        $credits = config("ai.plans.{$slug}.monthly_credits");
        $images = config("ai.plans.{$slug}.max_images_per_request");

        if ($credits === null) {
            Log::warning('AI plan has no monthly credit configuration; using the conservative fallback.', [
                'plan' => $slug,
            ]);
            $credits = config('ai.limits.fallback_monthly_credits', 25);
        }

        if ($images === null) {
            Log::warning('AI plan has no image limit configuration; using the conservative fallback.', [
                'plan' => $slug,
            ]);
            $images = config('ai.limits.default_photo_items', 4);
        }

        return [
            'slug' => $slug,
            'monthly_credits' => max(0, (int) $credits),
            'max_images_per_request' => max(1, (int) $images),
            'source' => 'config',
        ];
    }

    /**
     * A null image column means "inherit the slug's configured value"; a missing
     * value means the install is unconfigured, so use the conservative default
     * rather than treating it as no limit.
     */
    private function planImageLimit(string $slug, ?int $stored): int
    {
        if ($stored !== null) {
            return max(1, $stored);
        }

        $configured = config("ai.plans.{$slug}.max_images_per_request");

        if ($configured === null) {
            Log::warning('AI plan row has no image limit and no config fallback; using the conservative fallback.', [
                'plan' => $slug,
            ]);
            $configured = config('ai.limits.default_photo_items', 4);
        }

        return max(1, (int) $configured);
    }
}
