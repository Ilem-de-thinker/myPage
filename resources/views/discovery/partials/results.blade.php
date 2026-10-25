@php
    /**
     * The filtered state: what is currently narrowed to, the results, and the
     * pager. Swapped in as one fragment so the search form above never moves.
     *
     * @var \App\Domains\Directory\Http\Requests\DiscoveryRequest $filters
     * @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $results
     * @var int $total
     */
    // One row per active filter, each with the link that drops just that one.
    // Carrying the rest across is what makes a chip mean "refine", not "reset".
    $chips = [];

    if ($term = $filters->term()) {
        $chips[] = ['label' => 'Search: '.$term, 'remove' => array_diff_key($filters->activeFilters(), ['q' => null])];
    }

    if ($theme) {
        $chips[] = ['label' => 'Type: '.($theme === \App\Domains\Seller\Enums\Theme::Store ? 'Stores' : 'Services'), 'remove' => array_diff_key($filters->activeFilters(), ['type' => null])];
    }

    if ($city = $filters->city()) {
        $chips[] = ['label' => 'City: '.$city, 'remove' => array_diff_key($filters->activeFilters(), ['city' => null])];
    }

    if ($tier = $filters->tier()) {
        $chips[] = ['label' => 'Tier: '.$tier->shortLabel(), 'remove' => array_diff_key($filters->activeFilters(), ['tier' => null])];
    }
@endphp

<section class="section-y">
    <div class="shell">

        @if ($chips !== [])
            {{-- Active Filter Chips --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-8 pt-8 text-xs">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[var(--ink-3)] font-semibold">Active:</span>
                    @foreach ($chips as $chip)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink)]">
                            <span>{{ $chip['label'] }}</span>
                            <a href="{{ route('discovery.index', array_filter($chip['remove'], fn ($v) => $v !== null)) }}"
                               class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1"
                               aria-label="Remove the {{ $chip['label'] }} filter">×</a>
                        </span>
                    @endforeach
                    <a href="{{ route('discovery.index') }}" class="text-[var(--brand-text)] font-semibold hover:underline ml-2">
                        Clear all
                    </a>
                </div>
            </div>
        @endif

        @if ($results->isEmpty())
            {{-- EMPTY STATE --}}
            <div class="my-12">
                <div class="border-2 border-dashed border-[var(--rule)] rounded-[var(--radius-lg)] p-12 text-center max-w-2xl mx-auto bg-[var(--surface)]/30">
                    <div class="w-12 h-12 rounded-full bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center mx-auto mb-4 text-[var(--ink-3)]">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><line x1="8" x2="14" y1="11" y2="11"/></svg>
                    </div>

                    <h2 class="text-lg font-bold text-[var(--ink)] mb-2">No verified merchants found</h2>
                    <p class="t-body text-xs text-[var(--ink-2)] mb-6 max-w-md mx-auto leading-relaxed">
                        We couldn't find any storefronts or studios matching your current search parameters. Try removing some filters or searching for a broader term.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-8">
                        <a href="{{ route('discovery.index') }}" class="h-10 px-5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center hover:opacity-90 transition-opacity">
                            Reset All Filters
                        </a>
                        <a href="{{ route('discovery.index', ['type' => 'store']) }}" class="h-10 px-4 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] font-semibold text-xs flex items-center justify-center hover:bg-[var(--surface)] transition-colors">
                            Browse Every Store
                        </a>
                    </div>

                    <div class="pt-6 border-t border-[var(--rule)]">
                        <p class="t-meta mb-3 font-semibold">Suggested popular categories:</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            @foreach (\App\Shared\Support\PlatformNetwork::categories(4) as $suggestion)
                                <a href="{{ route('discovery.index', ['q' => $suggestion['name']]) }}" class="px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] text-xs text-[var(--ink)] hover:border-[var(--brand-edge)]">
                                    {{ $suggestion['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- DEFAULT FILTERED RESULTS CONTAINER --}}
            <div class="flex items-baseline justify-between mb-6">
                <div>
                    <h1 class="text-lg font-bold text-[var(--ink)]">Search Results</h1>
                    <p class="t-meta mt-0.5">Showing {{ $total }} verified {{ $total === 1 ? 'merchant' : 'merchants' }}{{ $city ? ' in '.$city : '' }}</p>
                </div>
                <span class="text-xs text-[var(--ink-3)]">Sorted by Rating &amp; Response Record</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($results as $page)
                    <x-platform.business-card :page="$page" />
                @endforeach
            </div>

            <x-platform.paginator :paginator="$results" />
        @endif

    </div>
</section>