<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AiLimits
{
    public function __construct(private readonly AiPlanLimits $plans) {}

    public function throttle(StorePage $page): void
    {
        $key = $this->key($page);
        $perMinute = $this->requestsPerMinute();

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $seconds = RateLimiter::availableIn($key);

            abort(429, "Too many Mercho requests. Try again in {$seconds} seconds.", ['Retry-After' => (string) $seconds]);
        }

        RateLimiter::hit($key, 60);
    }

    public function assertCanQueue(StorePage $page, int $count = 1): void
    {
        $this->assertWithinTaskCap($count);

        $active = $this->activeOperations($page);

        abort_if(
            $active + $count > $this->maxActiveOperations(),
            429,
            'This store already has the maximum number of Mercho tasks running. Wait for one to finish and try again.'
        );
    }

    public function assertWithinTaskCap(int $count): void
    {
        abort_if(
            $count > $this->maxBatchItems(),
            422,
            'This task can process at most '.$this->maxBatchItems().' items at a time.'
        );
    }

    public function availableSlots(StorePage $page): int
    {
        return max(0, $this->maxActiveOperations() - $this->activeOperations($page));
    }

    public function activeOperations(StorePage $page): int
    {
        return AiOperation::query()
            ->where('seller_id', $page->seller_id)
            ->whereIn('status', ['queued', 'running'])
            ->count();
    }

    public function maxBatchItems(): int
    {
        return max(1, (int) config('ai.limits.max_batch_items', 25));
    }

    /**
     * The effective per-request image allowance.
     *
     * The plan supplies the product decision, while the absolute and legacy
     * caps protect the provider from a misconfigured plan row. Keeping the
     * legacy cap means an installation that already tuned AI_MAX_PHOTO_ITEMS
     * can still lower the ceiling without editing every plan.
     */
    public function imageLimit(StorePage $page): int
    {
        return max(1, min(
            $this->plans->imageLimit($page),
            $this->absoluteMaxPhotoItems(),
            $this->maxPhotoItems(),
        ));
    }

    /**
     * Reject an upload before any file is staged.
     *
     * Runs separately from Laravel's `max` rule so the response is a normal
     * field error and can point the merchant at an upgrade instead of exposing
     * a raw provider-facing limit.
     */
    public function assertImageCount(StorePage $page, int $count): void
    {
        $limit = $this->imageLimit($page);

        if ($count <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'photos' => __('ai.image_limit', ['limit' => $limit]),
        ]);
    }

    /**
     * Legacy global ceiling. New code should use imageLimit(), which folds in
     * the plan allowance as well.
     */
    public function maxPhotoItems(): int
    {
        return max(1, (int) config('ai.limits.max_photo_items', 20));
    }

    private function absoluteMaxPhotoItems(): int
    {
        return max(1, (int) config('ai.limits.absolute_max_photo_items', 20));
    }

    public function maxActiveOperations(): int
    {
        return max(1, (int) config('ai.limits.max_active_operations', 50));
    }

    public function requestsPerMinute(): int
    {
        return max(1, (int) config('ai.limits.requests_per_minute', 30));
    }

    private function key(StorePage $page): string
    {
        return 'ai:limits:'.$page->seller_id;
    }
}
