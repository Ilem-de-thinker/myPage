<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Http\Requests\ProductFiltersRequest;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the buyer-facing product catalogue.
 *
 * This replaces an inline `when()` chain in the controller. Three reasons it earns
 * its own class:
 *
 *  1. The sidebar's numbers — price bounds, per-category counts — have to come
 *     from the *same* filters as the results, or the panel offers a choice that
 *     returns nothing. Two builders in one class cannot drift.
 *  2. Search-term escaping lives beside the search, once.
 *  3. It is testable without a request.
 *
 * The store's tenant scoping is not restated here. Product carries BelongsToSeller
 * and ResolveStorePage has already bound the page on a storefront route, so the
 * global scope does that work. Everything below is in addition to it.
 */
class ProductCatalogQuery
{
    /**
     * The price range the two boxes are drawn from.
     *
     * Deliberately from the page's whole active catalogue, ignoring every active
     * filter. Bounds derived from the current results would collapse towards a
     * single value the moment a narrow filter was applied, and the buyer could no
     * longer widen the range without first clearing the filter they were using —
     * the one control that would help.
     *
     * Read straight off the query builder rather than through the model, so the
     * aggregate row is not hydrated as a Product and does not trigger eager loads.
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
     * The page's categories with how many active products each holds.
     *
     * Counted under the search term but *not* under the category filter: a panel
     * that counted only what is already selected would show every other category
     * as empty, which reads as "there is nothing else here" and buries the way out
     * of the filter the buyer is currently in.
     *
     * Categories with nothing under them are dropped rather than shown at zero — a
     * filter that offers an empty result is worse than no filter.
     *
     * @return Collection<int, Category>
     */
    public function categoriesWithCounts(StorePage $page, ProductFiltersRequest $filters): Collection
    {
        $term = $filters->term();

        return Category::query()
            ->where('store_page_id', $page->id)
            ->withCount([
                'products as products_count' => fn (Builder $products) => $products
                    ->active()
                    ->search($term),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category): bool => $category->products_count > 0)
            ->values();
    }

    /**
     * @return LengthAwarePaginator<Product>
     */
    public function paginate(StorePage $page, ProductFiltersRequest $filters, int $perPage = 12): LengthAwarePaginator
    {
        // withQueryString() is what keeps page two showing the same filter.
        // Without it Laravel drops the query parameters and page two is the whole
        // catalogue again — the single most common bug in a filtered list.
        return $this->filtered($page, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * The filtered query, before pagination.
     *
     * @return Builder<Product>
     */
    public function filtered(StorePage $page, ProductFiltersRequest $filters): Builder
    {
        return $this->base($page)
            ->search($filters->term())
            ->inCategories($filters->categoryIds())
            ->pricedBetween($filters->minKobo(), $filters->maxKobo())
            ->sorted($filters->sort());
    }

    /**
     * How many active products the page has, filter or no filter.
     *
     * Drives the difference between "nothing matched what you asked for" and
     * "this shop has nothing listed yet" — two different sentences, and only the
     * first one is the shopper's fault.
     */
    public function totalProducts(StorePage $page): int
    {
        return $this->base($page)->toBase()->count();
    }

    /**
     * @return Builder<Product>
     */
    private function base(StorePage $page): Builder
    {
        return Product::query()
            ->with(['images', 'category'])
            ->active()
            ->where('products.store_page_id', $page->id);
    }
}
