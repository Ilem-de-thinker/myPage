<?php

namespace App\Domains\Directory\Services;

use App\Domains\Directory\Http\Requests\DiscoveryRequest;
use App\Domains\Seller\Enums\PriceTier;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The public discovery hub's query.
 *
 * Sits beside DirectoryQuery rather than replacing it. The two answer different
 * questions — "every published page by state and category" versus "every business
 * that has opted into being found, narrowed by how a buyer would narrow it" — and
 * the directory is already behind its own feature flag with its own tests. The one
 * rule they must never disagree on is stated twice, identically, on purpose: a
 * page that is not published, or that has not opted in, is not public.
 */
class DiscoveryQuery
{
    /**
     * How many businesses the marquee shows per column.
     *
     * Three columns of a dozen is a screen that never shows a seam. The number is
     * read from the view rather than guessed here, because a CSS animation that
     * translates by -50% needs the card list duplicated to loop seamlessly, and
     * the column height is what decides that.
     */
    public const MARQUEE_PER_COLUMN = 9;

    /**
     * @return Builder<StorePage>
     */
    public function base(): Builder
    {
        return StorePage::withoutGlobalScopes()
            ->discoverable();
    }

    /**
     * @return LengthAwarePaginator<StorePage>
     */
    public function paginate(DiscoveryRequest $filters, int $perPage = 24): LengthAwarePaginator
    {
        return $this->apply($this->base(), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * How many businesses match, so the heading can say whether this is everything
     * or a handful.
     */
    public function total(DiscoveryRequest $filters): int
    {
        return $this->apply($this->base(), $filters)->toBase()->count();
    }

    /**
     * How many businesses there are with no filters at all — the "everything on
     * the platform" figure the hero quotes.
     */
    public function totalDiscoverable(): int
    {
        return $this->base()->toBase()->count();
    }

    /**
     * The marquee's three columns.
     *
     * Deal the rows round-robin rather than slicing the list into thirds. Slicing
     * puts every similar business in the same column when the ordering is by theme
     * or name, and the reader sees one kind of shop three times instead of three
     * kinds of shop once each. Round-robin only holds because the columns run in
     * opposite directions and are offset — see the view.
     *
     * @param  list<StorePage>  $pages
     * @return list<list<StorePage>>
     */
    public function splitIntoColumns(array $pages, int $columns = 3): array
    {
        $buckets = array_fill(0, $columns, []);

        foreach (array_values($pages) as $index => $page) {
            $buckets[$index % $columns][] = $page;
        }

        // A column with nothing in it is removed rather than left as a gap: with
        // two businesses there is no three-column layout to show.
        return array_values(array_filter($buckets, fn (array $bucket): bool => $bucket !== []));
    }

    /**
     * The businesses the marquee scrolls, best candidates first.
     *
     * Featured first, then the busiest, then newest. Featured is a paid placement
     * and must actually reach the top of the first screen or nobody pays for it.
     *
     * @return Collection<int, StorePage>
     */
    public function marqueeRows(): Collection
    {
        return $this->base()
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->orderByDesc('view_count')
            ->orderByDesc('published_at')
            ->limit(self::MARQUEE_PER_COLUMN * 3)
            ->get();
    }

    /**
     * Cities that actually have a discoverable business in them.
     *
     * Derived from the rows rather than kept in a list, so the dropdown cannot
     * offer a city that returns nothing.
     *
     * @return Collection<int, string>
     */
    public function cities(?Theme $theme = null): Collection
    {
        return $this->base()
            ->when($theme, fn (Builder $q, Theme $theme) => $q->ofTheme($theme))
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    /**
     * How many discoverable businesses sit at each tier.
     *
     * @return Collection<int, int> tier => count
     */
    public function tierCounts(?Theme $theme = null): Collection
    {
        return $this->base()
            ->when($theme, fn (Builder $q, Theme $theme) => $q->ofTheme($theme))
            ->whereNotNull('price_tier')
            ->selectRaw('price_tier, COUNT(*) as aggregate')
            ->groupBy('price_tier')
            ->pluck('aggregate', 'price_tier')
            ->map(fn ($count): int => (int) $count);
    }

    /**
     * @return Collection<int, array{theme: Theme, count: int}>
     */
    public function typeCounts(): Collection
    {
        $counts = $this->base()
            ->selectRaw('theme, COUNT(*) as aggregate')
            ->groupBy('theme')
            ->pluck('aggregate', 'theme');

        return collect(Theme::cases())
            ->map(fn (Theme $theme): array => [
                'theme' => $theme,
                'count' => (int) ($counts[$theme->value] ?? 0),
            ])
            ->values();
    }

    /**
     * The businesses with the highest rating that have one, for the featured strip.
     *
     * @return Collection<int, StorePage>
     */
    public function topRated(int $limit = 3): Collection
    {
        return $this->base()
            ->where('review_count', '>', 0)
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, PriceTier>
     */
    public function tiersInUse(?Theme $theme = null): array
    {
        $counts = $this->tierCounts($theme);

        return array_values(array_filter(
            PriceTier::ordered(),
            fn (PriceTier $tier): bool => ($counts[$tier->value] ?? 0) > 0,
        ));
    }

    /**
     * The businesses on the marketing pages' social-proof strip.
     *
     * The same ordering the marquee uses, so a paid featured placement actually
     * reaches the landing page rather than only the hub. A strip shorter than the
     * platform has businesses is the honest answer on a fresh install.
     *
     * @return Collection<int, StorePage>
     */
    public function proofStrip(int $limit = 6): Collection
    {
        return $this->base()
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderByDesc('view_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * The cities with a discoverable business in them, busiest first.
     *
     * @return Collection<int, string>
     */
    public function busiestCities(int $limit = 6): Collection
    {
        return $this->base()
            ->whereNotNull('city')
            ->selectRaw('city, COUNT(*) as aggregate')
            ->groupBy('city')
            ->orderByDesc('aggregate')
            ->orderBy('city')
            ->limit($limit)
            ->pluck('city');
    }

    /**
     * @return Builder<StorePage>
     */
    private function apply(Builder $query, DiscoveryRequest $filters): Builder
    {
        if ($term = $filters->term()) {
            // Name, tagline and bio. Bio included here and not in DirectoryQuery on
            // purpose: on a hub the keyword is usually a *thing* ("nails",
            // "generator") and that word is far more likely to live in the
            // seller's own words than in their name.
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('tagline', 'like', $like)
                    ->orWhere('bio', 'like', $like);
            });
        }

        if ($theme = $filters->theme()) {
            $query->ofTheme($theme);
        }

        if ($city = $filters->city()) {
            $query->where('city', $city);
        }

        if ($tier = $filters->tier()) {
            $query->where('price_tier', $tier->value);
        }

        return match ($filters->input('sort')) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('review_count')->orderBy('name'),
            'newest' => $query->orderByDesc('published_at')->orderBy('name'),
            // The default: featured placement, then reputation, then traffic.
            default => $query
                ->orderByDesc('is_featured')
                ->orderByDesc('rating')
                ->orderByDesc('review_count')
                ->orderByDesc('view_count')
                ->orderBy('name'),
        };
    }
}
