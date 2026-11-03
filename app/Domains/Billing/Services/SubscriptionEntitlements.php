<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;

final class SubscriptionEntitlements
{
    public function __construct(private readonly SubscriptionAvailability $availability) {}

    public function assertCanCreateListing(StorePage $page): void
    {
        if (! $this->availability->enabled()) {
            return;
        }

        // Two concurrent creates at max-1 both pass a plain count. The lock
        // serialises the check against the subscription row, so the second
        // request sees the first one's insert before deciding.
        DB::transaction(function () use ($page): void {
            $subscription = Subscription::query()
                ->with('plan')
                ->where('seller_id', $page->seller_id)
                ->lockForUpdate()
                ->first();

            $limit = $subscription?->plan?->max_products
                ?? config('billing.plans.'.config('billing.trial_plan', 'trial').'.max_products');

            $used = $page->products()->count() + $page->services()->count();

            abort_if($used >= $limit, 403, "Your current plan allows up to {$limit} products or services. Upgrade to add another listing.");
        });
    }

    public function listingLimit(StorePage $page): ?int
    {
        if (! $this->availability->enabled()) {
            return null;
        }

        $subscription = Subscription::query()->with('plan')->where('seller_id', $page->seller_id)->first();

        // A missing subscription row is not "unlimited". Billing data that has
        // not caught up must fail closed to the entry plan, or a gap in the
        // subscription table silently hands out the largest allowance.
        return $subscription?->plan?->max_products
            ?? config('billing.plans.'.config('billing.trial_plan', 'trial').'.max_products');
    }

    public function assertCanCreateStaff(StorePage $page): void
    {
        $limit = $this->staffLimit($page);

        if ($limit === null) {
            return;
        }

        $used = $page->staffMembers()->count();

        abort_if($used >= $limit, 403, "Your current plan allows up to {$limit} staff member".($limit === 1 ? '' : 's').'. Upgrade to add another.');
    }

    public function staffLimit(StorePage $page): ?int
    {
        if (! $this->availability->enabled()) {
            return null;
        }

        $subscription = Subscription::query()->with('plan')->where('seller_id', $page->seller_id)->first();

        return $subscription?->plan?->max_staff
            ?? config('billing.plans.'.config('billing.trial_plan', 'trial').'.max_staff');
    }
}
