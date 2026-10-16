@php
    /**
     * The unfiltered state: three columns of businesses moving in opposite
     * directions, plus a static grid underneath for narrow screens.
     *
     * @var \Illuminate\Support\Collection<int, list<\App\Domains\Seller\Models\StorePage>> $columns
     * @var \Illuminate\Support\Collection<int, \App\Domains\Seller\Models\StorePage> $featured
     */
@endphp

@if ($columns === [])
    {{--
        Nobody has opted in yet. An empty marquee with no explanation reads as a
        broken page, and this is the state a brand-new install opens on — so it
        has to say something.
    --}}
    <section class="section-y">
        <div class="shell">
            <div class="border-2 border-dashed border-[var(--rule)] rounded-[var(--radius-lg)] p-12 text-center max-w-2xl mx-auto bg-[var(--surface)]/30">
                <div class="w-12 h-12 rounded-full bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center mx-auto mb-4 text-[var(--ink-3)]">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/></svg>
                </div>

                <h2 class="text-lg font-bold text-[var(--ink)] mb-2">Nobody has joined the directory yet.</h2>
                <p class="t-body text-xs text-[var(--ink-2)] mb-6 max-w-md mx-auto leading-relaxed">
                    Every merchant here has asked to be found and verified with escrow security. The first one could be yours.
                </p>

                <a href="{{ route('register') }}" class="inline-flex h-10 px-5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs items-center justify-center hover:opacity-90 transition-opacity">
                    Claim Your Storefront Handle
                </a>
            </div>
        </div>
    </section>
@else
    <!-- 3-COLUMN ASYNCHRONOUS MARQUEE WITH MANDATORY PAUSE/PLAY (WCAG 2.2.2) -->
    <section class="section-y border-b border-[var(--rule)] overflow-hidden">
    <div class="shell">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <p class="t-meta">Live Marketplace Stream</p>
                <h2 class="t-display-2 text-[var(--ink)] mt-1">Featured businesses across Nigeria</h2>
                {{-- How big the platform actually is, on the page that is about size. --}}
                <p class="t-meta mt-1">{{ number_format($totalDiscoverable) }} {{ $totalDiscoverable === 1 ? 'business is' : 'businesses are' }} selling here</p>
            </div>

            {{-- Visible WCAG 2.2.2 Pause/Play Button --}}
            <button type="button"
                    @click="marqueePaused = !marqueePaused"
                    class="self-start sm:self-auto inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-[var(--radius)] bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--paper)] transition-colors"
                    :aria-label="marqueePaused ? 'Resume scrolling storefront marquee' : 'Pause scrolling storefront marquee'">
                <span class="w-2 h-2 rounded-full" :class="marqueePaused ? 'bg-amber-500' : 'bg-[var(--brand)]'" aria-hidden="true"></span>
                <span x-text="marqueePaused ? 'Resume Marquee' : 'Pause Marquee'">Pause Marquee</span>
            </button>
        </div>

        {{-- Desktop 3-Column Marquee (36rem height, edge mask fades) --}}
        <div class="hidden lg:grid gap-6 h-[36rem] marquee" :class="{ 'is-paused': marqueePaused }"
             @class(['grid-cols-3' => count($columns) === 3, 'grid-cols-2' => count($columns) === 2, 'grid-cols-1' => count($columns) <= 1])>
            @foreach ($columns as $index => $column)
                {{--
                    Alternate direction and offset the downward column, so the
                    three read as one movement rather than three parallel loops.
                --}}
                <div @class([
                    'marquee-track-up',
                    'marquee-track-down -mt-24' => $index % 2 === 1,
                ])>
                    @foreach ($column as $page)
                        <x-platform.listing-card :page="$page" />
                    @endforeach

                    {{-- Duplicate Set for Continuous Loop (marked inert for screen readers) --}}
                    @foreach ($column as $page)
                        <div aria-hidden="true" inert>
                            <x-platform.listing-card :page="$page" :interactive="false" />
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Mobile / Tablet Fallback (<lg): Clean Static 2-Column Grid --}}
        <div class="lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($columns as $column)
                @foreach ($column as $page)
                    <x-platform.listing-card :page="$page" />
                @endforeach
            @endforeach
        </div>

    </div>
</section>

@if ($featured->isNotEmpty())
    {{-- CURATED COLLECTIONS GRID --}}
    <section class="section-y">
        <div class="shell">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <p class="t-meta">Curated Merchants</p>
                    <h2 class="t-display-2 text-[var(--ink)] mt-1">Featured Nigerian storefronts</h2>
                </div>
                <span class="t-meta hidden sm:block">All merchants verified with escrow security</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($featured as $page)
                    <x-platform.business-card :page="$page" />
                @endforeach
            </div>
        </div>
    </section>
@endif
@endif