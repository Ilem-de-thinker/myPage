<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Seller\Models\StorePage;

final class SubscriptionEntitlements
{
    public function __construct(private readonly SubscriptionAvailability $availability) {}

    public function assertCanCreateListing(StorePage $page): void
    {
        $limit = $this->listingLimit($page);

        if ($limit === null) {
            return;
        }

        $used = $page->products()->count() + $page->services()->count();

        abort_if($used >= $limit, 403, "Your current plan allows up to {$limit} products or services. Upgrade to add another listing.");
    }

    public function listingLimit(StorePage $page): ?int
    {
        if (! $this->availability->enabled()) {
            return null;
        }

        $subscription = Subscription::query()->with('plan')->where('seller_id', $page->seller_id)->first();

        return $subscription?->plan?->max_products;
    }
}
