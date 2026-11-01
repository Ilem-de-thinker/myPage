<?php

namespace App\Domains\Directory\Services;

use App\Domains\Seller\Models\StorePage;

final class DirectoryPlacement
{
    public function canFeature(StorePage $page): bool
    {
        $subscription = $page->seller?->subscriptions()->with('plan')->first();

        // Featured placement is a paid benefit. Grace still counts as paid time,
        // but an expired or cancelled subscription does not.
        return $subscription !== null
            && $subscription->canWrite()
            && in_array($subscription->plan?->slug, config('billing.directory_featured_plans', []), true);
    }
}
