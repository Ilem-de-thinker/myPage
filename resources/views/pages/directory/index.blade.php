@php
    /**
     * The national directory, rendered through the platform shell.
     *
     * One view serves the whole directory and every /directory/{state} and
     * /directory/{state}/{category} URL beneath it, so the heading is whatever
     * the route resolved to rather than anything the template decides.
     *
     * @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $pages
     * @var \Illuminate\Support\Collection $states
     * @var \Illuminate\Support\Collection $categories
     * @var array<string, string|null> $filters
     * @var int $total
     */
    $state = $filters['state'] ?? null;
    $category = $filters['category'] ?? null;
@endphp

@extends('layouts.platform')

@section('title', ($heading ?? 'Nigerian Merchant Directory').' — mypage.ng')
@section('description', 'Browse verified storefronts, artisanal makers, fashion houses, and appointment studios across all 36 states.')

@section('content')

    {{-- DIRECTORY HEADER --}}
    <section class="section-y bg-[var(--surface)] border-b border-[var(--rule)]">
        <div class="shell">
            <div class="max-w-3xl mb-8">
                <p class="t-meta mb-2">National Merchant Registry</p>
                <h1 class="t-display-1 text-[var(--ink)]">
                    {{ $heading ?? 'Nigerian Merchant Directory' }}
                </h1>
                <p class="t-body-lg text-[var(--ink-2)] t-measure mt-3">
                    Browse verified storefronts, artisanal makers, fashion houses, and appointment studios across all 36 states.
                </p>
                {{-- How many actually made it into this view of the directory. --}}
                <p class="t-meta mt-2">{{ number_format($total) }} {{ $total === 1 ? 'page' : 'pages' }} listed{{ $state ? ' in '.ucfirst(str_replace('-', ' ', $state)) : '' }}.</p>
            </div>

            {{-- Filter Row --}}
            <form action="{{ route('directory.index') }}" method="GET" class="p-4 rounded-[var(--radius-lg)] bg-[var(--paper)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
                @if (($filters['theme'] ?? null))
                    <input type="hidden" name="theme" value="{{ $filters['theme'] }}">
                @endif
                @if ($category)
                    <input type="hidden" name="category" value="{{ $category }}">
                @endif

                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-6 relative">
                        <label for="dir-q" class="sr-only">Search directory</label>
                        <input type="text"
                               id="dir-q"
                               name="q"
                               value="{{ $filters['q'] ?? '' }}"
                               placeholder="Filter by business name, state, or product..."
                               class="w-full h-11 pl-10 pr-4 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                        <svg class="w-4 h-4 text-[var(--ink-3)] absolute left-3.5 top-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>

                    <div class="md:col-span-3">
                        <label for="dir-state" class="sr-only">Filter by state</label>
                        <select id="dir-state"
                                name="state"
                                class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                            <option value="">All Nigerian States (36 + FCT)</option>
                            @foreach ($states as $row)
                                <option value="{{ $row }}" @selected($state === $row)>{{ $row }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <button type="submit"
                                class="w-full h-11 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity">
                            <span>Filter Directory</span>
                        </button>
                    </div>
                </div>
            </form>

            {{-- Category Filter Tabs as Real Links with Counts --}}
            @if ($categories->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 mt-6" role="tablist" aria-label="Directory categories">
                    <a href="{{ route('directory.index', array_filter(['q' => $filters['q'] ?? null, 'state' => $state])) }}"
                       @class([
                           'px-3.5 py-1.5 rounded-[var(--radius-pill)] text-xs',
                           'bg-[var(--brand)] text-[var(--brand-ink)] font-bold shadow-sm' => $category === null,
                           'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)] font-semibold transition-colors' => $category !== null,
                       ])
                       @if ($category === null) aria-current="page" @endif>
                        All Merchants ({{ $total }})
                    </a>
                    @foreach ($categories as $row)
                        <a href="{{ route('directory.state', ['state' => $state ?: 'lagos', 'category' => $row->slug]) }}"
                           @class([
                               'px-3.5 py-1.5 rounded-[var(--radius-pill)] text-xs',
                               'bg-[var(--brand)] text-[var(--brand-ink)] font-bold shadow-sm' => $category === $row->slug,
                               'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)] font-semibold transition-colors' => $category !== $row->slug,
                           ])
                           @if ($category === $row->slug) aria-current="page" @endif>
                            {{ $row->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- DIRECTORY CARDS GRID --}}
    <section class="section-y">
        <div class="shell">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($pages as $page)
                    <x-platform.business-card :page="$page" />
                @empty
                    <div class="col-span-full border-2 border-dashed border-[var(--rule)] rounded-[var(--radius-lg)] p-12 text-center max-w-2xl mx-auto bg-[var(--surface)]/30">
                        <div class="w-12 h-12 rounded-full bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center mx-auto mb-4 text-[var(--ink-3)]">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </div>

                        <h2 class="text-lg font-bold text-[var(--ink)] mb-2">Nothing here yet.</h2>
                        <p class="t-body text-xs text-[var(--ink-2)] mb-6 max-w-md mx-auto leading-relaxed">
                            @if (($filters['q'] ?? null) || $category)
                                Try a broader search, or clear the filters.
                            @else
                                No published pages in this state yet.
                            @endif
                        </p>

                        <a href="{{ route('directory.index') }}" class="inline-flex h-10 px-5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs items-center justify-center hover:opacity-90 transition-opacity">
                            See everything
                        </a>
                    </div>
                @endforelse
            </div>

            @if ($pages->hasPages())
                <x-platform.paginator :paginator="$pages" label="pages" />
            @endif
        </div>
    </section>

@endsection