<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Http\Requests\ServiceFiltersRequest;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Enums\PriceTier;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the buyer-facing service directory.
 *
 * The service twin of ProductCatalogQuery, and deliberately the same shape: a
 * filter object in, a paginator plus the numbers the filter panel needs out. The
 * seller who switches between the two themes should not have to learn a second
 * filtering model.
 *
 * Tenant scoping is again left to the BelongsToSeller global scope, which
 * ResolveStorePage's binding has already narrowed to this page.
 */
class ServiceCatalogQuery
{
    /**
     * Price range for the two boxes, from the page's whole active directory and
     * not from the filtered set — for the same reason as the store's, and written
     * out in ProductCatalogQuery::priceBounds().
     *
     * @return array{min: int, max: int}
     */
    public function priceBounds(StorePage $page): array
    {
        $bounds = $this->base($page)
            ->toBase()
            ->selectRaw('MIN(price_kobo) as min_price, MAX(price_kobo) as max_price')
            ->first();

        return [
            'min' => (int) ($bounds->min_price ?? 0),
            'max' => (int) ($bounds->max_price ?? 0),
        ];
    }

    /**
     * How many active services sit at each price tier.
     *
     * A page sells different kinds of thing at different prices — a ₦3,000
     * braiding and a ₦90,000 bridal package — so "most booked" means nothing on
     * its own. The tier is how the cards are grouped, and the breakdown under the
     * heading is what tells a buyer which card is the one they are looking for.
     *
     * Counted under the term and the price band, so the tiers describe what is
     * actually on screen rather than the page in the abstract.
     *
     * @return array<int, array{tier: PriceTier, count: int}>
     */
    public function tierBreakdown(StorePage $page, ServiceFiltersRequest $filters): array
    {
        $counts = $this->narrowed($page, $filters)
            ->toBase()
            ->selectRaw('CASE
                    WHEN price_kobo < 1000000 THEN 1
                    WHEN price_kobo < 5000000 THEN 2
                    WHEN price_kobo < 20000000 THEN 3
                    ELSE 4
                END as tier, COUNT(*) as aggregate')
            ->groupBy('tier')
            ->pluck('aggregate', 'tier');

        return array_map(
            fn (PriceTier $tier): array => [
                'tier' => $tier,
                'count' => (int) ($counts[$tier->value] ?? 0),
            ],
            PriceTier::ordered(),
        );
    }

    /**
     * The billing units actually in use on this page.
     *
     * Only the ones that exist: a "per hour" pill on a page where nothing is sold
     * per hour is a dead control that costs a tap and returns the same list.
     *
     * @return Collection<int, BillingUnit>
     */
    public function availableUnits(StorePage $page, ServiceFiltersRequest $filters): Collection
    {
        return $this->narrowed($page, $filters)
            ->toBase()
            ->distinct()
            ->orderBy('billing_unit')
            ->pluck('billing_unit')
            ->map(fn (string $unit): ?BillingUnit => BillingUnit::tryFrom($unit))
            ->filter()
            ->values();
    }

    /**
     * @return LengthAwarePaginator<Service>
     */
    public function paginate(StorePage $page, ServiceFiltersRequest $filters, int $perPage = 9): LengthAwarePaginator
    {
        return $this->filtered($page, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The filtered directory, before pagination.
     *
     * @return Builder<Service>
     */
    public function filtered(StorePage $page, ServiceFiltersRequest $filters): Builder
    {
        $query = $this->narrowed($page, $filters)
            ->when($filters->featuredOnly(), fn (Builder $q) => $q->featuredOnly())
            ->sorted($filters->sort());

        return $query;
    }

    /**
     * How many active services the page has, filter or no filter.
     */
    public function totalServices(StorePage $page): int
    {
        return $this->base($page)->toBase()->count();
    }

    /**
     * Everything except the sort and the featured toggle.
     *
     * @return Builder<Service>
     */
    private function narrowed(StorePage $page, ServiceFiltersRequest $filters): Builder
    {
        return $this->base($page)
            ->search($filters->term())
            ->pricedBetween($filters->minKobo(), $filters->maxKobo())
            ->withinDuration($filters->maxDurationMinutes())
            ->when($filters->unit(), fn (Builder $q, BillingUnit $unit) => $q->pricedPer($unit));
    }

    /**
     * @return Builder<Service>
     */
    private function base(StorePage $page): Builder
    {
        return Service::query()
            ->with('staff')
            ->where('services.store_page_id', $page->id)
            ->where('is_active', true);
    }
}
