<?php

namespace App\Domains\Directory\Services;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds the directory listing.
 *
 * Lives behind one class so the published-only rule is stated once. A controller
 * that forgot `where('status', published)` would put a suspended store in front of
 * the public, and the omission would not look like a bug in the controller.
 */
class DirectoryQuery
{
    /**
     * Only published pages, ever. Drafts are unfinished and suspended pages are
     * under dispute; neither belongs in a directory a stranger is reading.
     */
    public function base(): Builder
    {
        return StorePage::withoutGlobalScopes()
            ->where('status', StoreStatus::Published)
            ->whereNotNull('published_at');
    }

    /**
     * @return LengthAwarePaginator<StorePage>
     */
    public function paginate(Request $request, int $perPage = 24): LengthAwarePaginator
    {
        return $this->apply($this->base(), $request)->paginate($perPage)->withQueryString();
    }

    public function total(?Request $request = null): int
    {
        $query = $this->base();

        if ($request !== null) {
            $query = $this->apply($query, $request);
        }

        return $query->count();
    }

    /**
     * Distinct states that actually have a page, so the filter never offers a
     * choice that returns nothing.
     *
     * @return Collection<int, string>
     */
    public function states(): Collection
    {
        return $this->base()
            ->whereNotNull('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        // Categories attached to at least one published page, so the filter
        // never offers a choice that returns nothing. The subquery selects the
        // page ids explicitly: `select *` here is a SQL error, not a shortcut.
        return Category::query()
            ->whereIn('categories.store_page_id', function ($query): void {
                $query->select('store_pages.id')->from('store_pages')
                    ->where('store_pages.status', StoreStatus::Published->value)
                    ->whereNotNull('store_pages.published_at');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * Turn a URL slug back into a stored state name: /directory/lagos ->
     * 'Lagos'. Returns null when nothing matches, which the caller treats as
     * "no such place" rather than passing a slug through to a state filter.
     */
    public function stateSlug(string $slug): ?string
    {
        $needle = str_replace('-', ' ', mb_strtolower($slug));

        return $this->base()->pluck('state')
            ->first(fn (?string $state) => $state !== null
                && mb_strtolower($state) === $needle);
    }

    private function apply(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->input('q', ''));

        if ($search !== '') {
            // Name and tagline only. Searching bio would match every page that
            // happens to mention delivery, which is not what someone browsing a
            // directory is asking.
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('tagline', 'like', $like));
        }

        $theme = $request->input('theme');

        if (in_array($theme, array_column(Theme::cases(), 'value'), true)) {
            $query->where('theme', $theme);
        }

        if ($state = $request->input('state')) {
            $query->where('state', $state);
        }

        if ($city = $request->input('city')) {
            $query->where('city', $city);
        }

        if ($category = $request->input('category')) {
            // Only pages that actually carry products in that category, so a
            // category filter never mixes a hair salon into a fabric shop.
            $query->whereExists(function ($exists) use ($category): void {
                $exists->selectRaw(1)
                    ->from('categories')
                    ->join('products', 'products.category_id', '=', 'categories.id')
                    ->whereColumn('categories.store_page_id', 'store_pages.id')
                    ->where('categories.slug', $category);
            });
        }

        // Verified pages first, then newest. A directory that is alphabetical
        // buries every new seller, and a verified one is the reason a buyer
        // trusts the list.
        return $query->orderByDesc('verified_at')->orderByDesc('published_at')->orderBy('name');
    }

    /**
     * A compact count for a page card: how much is actually on offer.
     */
    public function itemCount(StorePage $page): int
    {
        return $page->theme === Theme::Store
            ? Product::withoutGlobalScopes()->where('store_page_id', $page->getKey())->count()
            : 0;
    }
}
