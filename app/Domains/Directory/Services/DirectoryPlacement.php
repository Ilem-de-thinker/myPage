<?php

namespace App\Domains\Directory\Services;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Seller\Models\StorePage;

final class DirectoryPlacement
{
    public function canFeature(StorePage $page): bool
    {
        return $page->seller()
            ->whereHas('subscriptions', function ($query): void {
                $query->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value, SubscriptionStatus::PastDue->value])
                    ->whereHas('plan', fn ($plan) => $plan->whereIn('slug', config('billing.directory_featured_plans', [])));
            })
            ->exists();
    }
}
