<?php

namespace App\Shared\Support;

use App\Domains\Catalog\Models\Category;
use App\Domains\Directory\Services\DiscoveryQuery;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Collection;

/**
 * What the platform says about itself, read from the platform.
 *
 * The marketing pages claim scale — "36 states", "1,400 merchants", a row of
 * businesses — and every one of those claims has to come out of the database or
 * it is a number nobody can audit. This is where they come from, so the landing
 * page, the footer and the discovery suggestions all quote the same rows.
 */
final class PlatformNetwork
{
    /**
     * The businesses on the social-proof strip.
     *
     * Best candidates first, the same ordering the marquee uses: a featured
     * placement that does not reach the strip is not a placement.
     *
     * @return Collection<int, StorePage>
     */
    public static function merchants(int $limit = 6): Collection
    {
        return app(DiscoveryQuery::class)->proofStrip($limit);
    }

    /**
     * The cities with a discoverable business in them, busiest first.
     *
     * @return Collection<int, string>
     */
    public static function cities(int $limit = 6): Collection
    {
        return app(DiscoveryQuery::class)->busiestCities($limit);
    }

    /**
     * The categories buyers actually search for.
     *
     * Only categories that still hold something, and never one whose products
     * are all drafts: suggesting a search that returns nothing is the one thing a
     * suggestion row must not do.
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    public static function categories(int $limit = 4): Collection
    {
        return Category::query()
            ->whereHas('products', fn ($query) => $query->active())
            ->withCount(['products as live_products_count' => fn ($query) => $query->active()])
            ->orderByDesc('live_products_count')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'count' => (int) $category->live_products_count,
            ])
            ->values();
    }

    /**
     * How many published pages are running on a theme right now.
     */
    public static function publishedCount(Theme $theme): int
    {
        // discoverable() rather than a bare status check: a count of "stores on
        // mypage.ng" that included suspended or opted-out shops would be a
        // number the hub itself would refuse to show.
        return app(DiscoveryQuery::class)->base()->ofTheme($theme)->toBase()->count();
    }
}
