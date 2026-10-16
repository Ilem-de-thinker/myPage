@php
    /**
     * The discovery hub.
     *
     * Two states, one route. Unfiltered, the page is the marquee — everything on
     * the platform, moving. As soon as anything is picked, it is a result grid.
     * A live request swaps between the two without navigating, so the filter bar
     * never moves under the pointer.
     *
     * Every control on this page is an ordinary link to a real URL. That is the
     * whole design: with JavaScript off, or with fetch blocked, the hub is a set
     * of working links and the address bar is always the truth about what is on
     * screen. The interceptor below is only an accelerant — any failure falls
     * through to a real navigation, because a filter that silently does nothing
     * is worse than one that takes a moment.
     *
     * @var \App\Domains\Directory\Http\Requests\DiscoveryRequest $filters
     * @var \App\Domains\Seller\Enums\Theme|null $theme
     */
    $hubPath = rtrim(parse_url(route('discovery.index'), PHP_URL_PATH) ?: '/discover', '/');
    $term = $filters->term();
@endphp

@extends('layouts.platform')

@section('title', ($filters->hasFilters() ? 'Search results' : 'Discover verified merchants').' — mypage.ng')
@section('description', 'Find shops taking payments and people you can book across Nigeria. Search by what you need, or by city.')
@section('body-state', '{ mobileNavOpen: false }')

@section('content')

<div x-data="{
        loading: false,
        marqueePaused: false,
        searchQuery: @js($term ?? ''),
        hubPath: @js($hubPath),
        async apply(link) {
            const url = new URL(link.href, window.location.origin);
            this.loading = true;
            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Partial': 'results',
                        'Accept': 'text/html',
                    },
                });
                if (!response.ok) throw new Error(response.status);
                this.$refs.content.innerHTML = await response.text();
                window.history.pushState({}, '', url);
            } catch (error) {
                window.location.href = url.toString();
            } finally {
                this.loading = false;
            }
        },
        intercept(event) {
            // A modified click means the visitor wants a new tab, and a new
            // tab is the correct outcome for a modified click.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download')) return;

            const url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) return;
            if (url.pathname.replace(/\/$/, '') !== this.hubPath) return;
            // Same URL: there is nothing to swap, so let the browser do nothing.
            if (url.href === window.location.href) return;

            event.preventDefault();
            this.apply(link);
        },
    }"
     x-on:click="intercept($event)">

    {{-- DISCOVERY HERO WITH OFFSET RADIAL WASHES --}}
    <section class="section-y relative overflow-hidden bg-gradient-to-b from-[var(--surface)] to-[var(--paper)] border-b border-[var(--rule)]">
        <!-- Radial washes offset: brand at 25%, warm orange at 78% -->
        <div class="absolute -top-24 left-[25%] -translate-x-1/2 w-[34rem] h-[34rem] rounded-full bg-[var(--brand-wash-2)] blur-3xl pointer-events-none -z-10" aria-hidden="true"></div>
        <div class="absolute -top-12 left-[78%] -translate-x-1/2 w-[28rem] h-[28rem] rounded-full bg-orange-500/10 blur-3xl pointer-events-none -z-10" aria-hidden="true"></div>

        <div class="shell">
            <div class="max-w-3xl mb-8">
                <p class="t-meta mb-2">Verified Merchant Network</p>
                <h1 class="t-display-1 text-[var(--ink)]">
                    Discover verified Nigerian commerce.
                </h1>
                <p class="t-body-lg text-[var(--ink-2)] t-measure mt-3">
                    Search artisanal craft, high fashion, bespoke bakeries, and appointment studios. Every merchant is verified with direct WhatsApp ordering and escrow guarantee.
                </p>
            </div>

            {{-- Type Switcher Pills as Real Links with Counts & aria-current --}}
            <div class="flex flex-wrap items-center gap-2 mb-6" role="tablist" aria-label="Filter merchant types">
                @php $types = array_merge([[null, 'Everything']], collect($typeCounts)->map(fn (array $row): array => [$row['theme'], $row['theme']->value === 'store' ? 'Stores' : 'Services & Studios'])->all()); @endphp
                @foreach ($types as [$value, $label])
                    @php $count = $value === null ? $totalDiscoverable : ($typeCounts->firstWhere('theme', $value)['count'] ?? 0); @endphp
                    <a href="{{ $value === null ? route('discovery.index') : route('discovery.index', ['type' => $value->value]) }}"
                       @class([
                           'inline-flex items-center gap-2 px-4 py-2 rounded-[var(--radius-pill)] text-xs',
                           'bg-[var(--brand)] text-[var(--brand-ink)] font-bold shadow-sm' => $theme === $value,
                           'bg-[var(--card)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)] font-semibold transition-colors' => $theme !== $value,
                       ])
                       @if ($theme === $value) aria-current="page" @endif>
                        <span>{{ $label }}</span>
                        <span @class([
                            'px-1.5 py-0.2 rounded-full text-[11px] tabular-nums',
                            'bg-white/20' => $theme === $value,
                            'bg-[var(--surface)] text-[var(--ink-3)]' => $theme !== $value,
                        ])>{{ $count }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Search Row (Real GET Form with Clear Button + City Select + Search) --}}
            <form action="{{ route('discovery.index') }}" method="GET" class="p-4 rounded-[var(--radius-lg)] bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] max-w-4xl">
                @if ($theme)
                    <input type="hidden" name="type" value="{{ $theme->value }}">
                @endif

                <div class="flex flex-col md:flex-row gap-3">

                    {{-- Keyword with Clear Button --}}
                    <div class="relative flex-1">
                        <label for="discover-q" class="sr-only">Search keyword or merchant</label>
                        <input type="text"
                               id="discover-q"
                               name="q"
                               value="{{ $term }}"
                               x-model="searchQuery"
                               placeholder="Search couturiers, leather guilds, studios..."
                               class="w-full h-12 pl-10 pr-9 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                        <svg class="w-4 h-4 text-[var(--ink-3)] absolute left-3.5 top-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <button type="button"
                                x-show="searchQuery.length > 0"
                                x-cloak
                                @click="searchQuery = ''"
                                class="absolute right-3.5 top-3.5 text-[var(--ink-3)] hover:text-[var(--ink)] text-sm font-bold"
                                aria-label="Clear search input">
                            ✕
                        </button>
                    </div>

                    {{-- City Select Dropdown --}}
                    <div class="md:w-56">
                        <label for="discover-city" class="sr-only">Select Nigerian city</label>
                        <select id="discover-city"
                                name="city"
                                class="w-full h-12 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                            <option value="">All Nigerian Cities</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @selected($filters->city() === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit"
                            class="h-12 px-6 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <span>Search Merchants</span>
                    </button>
                </div>

                @if ($tiers !== [])
                    {{-- Tier Pills Row --}}
                    <div class="flex flex-wrap items-center gap-3 pt-4 mt-4 border-t border-[var(--rule)] text-xs">
                        <span class="text-[var(--ink-3)] font-semibold">Price Tiers:</span>
                        <div class="flex items-center gap-1.5">
                            @foreach ($tiers as $tier)
                                <a href="{{ route('discovery.index', array_filter(['type' => $theme?->value, 'tier' => $tier->value], fn ($v) => $v !== null)) }}"
                                   @class([
                                       'px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] border text-[var(--ink)] hover:border-[var(--brand-edge)] font-mono font-bold',
                                       'border-[var(--brand)]' => $filters->tier() === $tier,
                                       'border-[var(--rule)]' => $filters->tier() !== $tier,
                                   ])
                                   @if ($filters->tier() === $tier) aria-current="true" @endif
                                   aria-label="{{ $tier->label() }} tier: {{ $tier->description() }}">{{ $tier->shortLabel() }} <span class="font-normal font-sans text-[11px] text-[var(--ink-3)]">{{ $tier->rangeLabel() }}</span></a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </form>

        </div>
    </section>

    {{--
        The one swappable region. A first request renders the whole page; a live
        filter request asks the same URL for this container alone and Alpine
        replaces its contents, so the search bar above never moves.
    --}}
    <div x-ref="content" :class="{ 'opacity-60 transition-opacity': loading }">
        @include($filters->hasFilters() ? 'discovery.partials.results' : 'discovery.partials.marquee')
    </div>

</div>

@endsection