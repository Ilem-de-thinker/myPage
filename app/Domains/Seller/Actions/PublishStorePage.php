<?php

namespace App\Domains\Seller\Actions;

use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Events\StorePagePublished;
use App\Domains\Seller\Exceptions\NotReadyToPublish;
use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Services\PublishReadiness;
use Illuminate\Support\Facades\DB;

/**
 * Puts a seller's page in front of buyers. Refuses while anything blocking is
 * unfinished, so nobody publishes a blank page and wonders why nobody visits.
 */
class PublishStorePage
{
    public function __construct(private readonly PublishReadiness $readiness) {}

    public function handle(StorePage $page): StorePage
    {
        $blockers = $this->readiness->blockers($this->readiness->checks($page));

        if ($blockers !== []) {
            throw new NotReadyToPublish($blockers);
        }

        DB::transaction(function () use ($page) {
            // Saving goes through StorePageObserver, which drops the cached
            // storefront so the page appears on the very next request.
            $page->update([
                'status' => StoreStatus::Published,
                'published_at' => $page->published_at ?? now(),
            ]);
        });

        event(new StorePagePublished($page));

        return $page;
    }
}
