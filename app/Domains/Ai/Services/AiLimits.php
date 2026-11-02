<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\RateLimiter;

class AiLimits
{
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

    public function maxPhotoItems(): int
    {
        return max(1, (int) config('ai.limits.max_photo_items', 12));
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
