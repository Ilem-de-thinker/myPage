{{--
    The service directory: the seller's own intro block, then the rate card.

    The intro block still renders above the header. Their words are the first thing
    a visitor should read; a filter cannot replace that.

    Ported from new_ui/service — the design filtered by bridal/editorial/gele with
    a client-side x-show. Services have no categories here, so the pills became the
    filters this page actually has: how long you can spare, and what a price is per.
    They stay server-side, because a list that hides rows the server did not send
    is a list that cannot be paginated or linked to.
--}}
<x-themes.shared.layout :page="$page">
  @php
    $storefront = ['slug' => $page->slug];
    $home = \App\Shared\Support\StoreUrl::homePath($page);
    $action = \App\Shared\Support\StoreUrl::path('storefront.services', $storefront);
    $total = $services->total();

    // The design's <h1> only prints when the seller has not written their own.
    $hasIntroHeading = $blocks->contains(fn ($block) => $block->key === 'services_intro' && $block->field('heading'));
  @endphp

  @foreach ($blocks as $block)
    <x-themes.service.blocks.services.intro :page="$page" :block="$block" />
  @endforeach

  <div class="shell py-12">

    {{-- Breadcrumbs --}}
    <nav class="flex items-center gap-2 text-xs text-[var(--ink-3)] mb-6" aria-label="Breadcrumb">
      <a href="{{ $home }}" class="hover:text-[var(--ink)] transition-colors">Home</a>
      <span aria-hidden="true">/</span>
      <span class="text-[var(--ink)] font-semibold" aria-current="page">Service Menu</span>
    </nav>

    {{-- Page header --}}
    <div class="max-w-2xl mb-10">
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold mb-4">
        <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
        {{ now()->year }} Sovereign Rate Card
      </span>

      @unless ($hasIntroHeading)
        <h1 class="t-display-1 text-[var(--ink)]">Services, Packages &amp; Masterclasses.</h1>
      @endunless

      <p class="t-body-lg text-[var(--ink-2)] mt-4">
        Every booking includes clear availability, service details, and secure payment on any deposit.
      </p>
    </div>

    {{--
        Search. The design has no search box on this page, but the directory has
        always had one and a rate card nobody can search is a rate card they scroll.
    --}}
    <form method="GET" action="{{ $action }}"
          x-data="serviceSearch(@jsExpr(['term' => $filters->term() ?? '']))"
          @input.debounce.350ms="search()"
          class="pb-8 mb-8 border-b border-[var(--rule)]">

      <div class="relative max-w-xl">
        <label for="service-q" class="sr-only">Search services</label>
        <input id="service-q" type="search" name="q" x-model="term"
               placeholder="What are you booking today?"
               autocomplete="off"
               class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">

        <button type="submit"
                class="absolute inset-y-0 right-1.5 my-1.5 flex items-center rounded-[var(--radius)] px-4 text-xs font-bold"
                style="background: var(--brand); color: var(--brand-ink)">
          Search
        </button>
      </div>

      {{-- Filter pills, in the design's shape. Every active filter is carried into
           the submission so sorting later does not throw the search away. --}}
      <div class="flex flex-wrap items-center gap-2 mt-6" role="group" aria-label="Service filters">
        @php
            $allQuery = array_diff_key($filters->activeFilters(), ['duration' => null, 'unit' => null]);
        @endphp

        <a href="{{ $action.'?'.http_build_query($allQuery) }}"
           @class([
               'px-4 py-2 rounded-[var(--radius-pill)] text-xs transition-colors',
               'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' => $filters->maxDurationMinutes() === null && $filters->unit() === null,
               'bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]' => $filters->maxDurationMinutes() !== null || $filters->unit() !== null,
           ])
           @if ($filters->maxDurationMinutes() === null && $filters->unit() === null) aria-current="true" @endif>
          All Packages ({{ $totalServices }})
        </a>

        @foreach ($durationOptions as $value => $minutes)
          @php
            $isActive = (string) $filters->input('duration') === (string) $value;
            $query = $isActive
                ? array_diff_key($filters->activeFilters(), ['duration' => null])
                : array_merge($filters->activeFilters(), ['duration' => $value]);
            $label = match (true) {
                $minutes < 60 => "Under {$minutes} min",
                $minutes === 60 => 'Up to 1 hour',
                default => 'Up to '.intdiv($minutes, 60).' hours',
            };
          @endphp
          <a href="{{ $action.'?'.http_build_query($query) }}"
             @class([
                 'px-4 py-2 rounded-[var(--radius-pill)] text-xs transition-colors',
                 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' => $isActive,
                 'bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]' => ! $isActive,
             ])
             @if ($isActive) aria-current="true" @endif>
            {{ $label }}
          </a>
        @endforeach

        @foreach ($units as $unit)
          @php
            $isActive = $filters->unit() === $unit;
            $query = $isActive
                ? array_diff_key($filters->activeFilters(), ['unit' => null])
                : array_merge($filters->activeFilters(), ['unit' => $unit->value]);
          @endphp
          <a href="{{ $action.'?'.http_build_query($query) }}"
             @class([
                 'px-4 py-2 rounded-[var(--radius-pill)] text-xs transition-colors',
                 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' => $isActive,
                 'bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]' => ! $isActive,
             ])
             @if ($isActive) aria-current="true" @endif>
            {{ $unit->label() }}
          </a>
        @endforeach
      </div>
    </form>

    {{-- The tier breakdown: a page sells a small service and a large one, and
         "most booked" means nothing until the reader can see which band a card is
         in. Counted under the term and the band, so it describes what is on screen. --}}
    @if ($totalServices > 1)
      <div class="mb-10 flex flex-wrap items-center gap-x-8 gap-y-4 pb-6 border-b border-[var(--rule)]">
        <div>
          <p class="t-title">
            {{ $total }} {{ $total === 1 ? 'service' : 'services' }}
            @if ($filters->hasFilters())
              <span class="text-[var(--ink-3)]">of {{ $totalServices }}</span>
            @endif
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
          @foreach ($tiers as $tierRow)
            @if ($tierRow['count'] > 0)
              <span class="t-meta inline-flex items-center gap-1.5">
                <span class="font-semibold" style="color: var(--brand-text)">{{ $tierRow['tier']->shortLabel() }}</span>
                {{ $tierRow['count'] }} {{ $tierRow['count'] === 1 ? 'service' : 'services' }}
              </span>
            @endif
          @endforeach
        </div>

        <form method="GET" action="{{ $action }}" class="ml-auto">
          @foreach ($filters->activeFilters() as $key => $value)
            @continue($key === 'sort')
            @foreach ((array) $value as $item)
              <input type="hidden" name="{{ $key }}" value="{{ $item }}">
            @endforeach
          @endforeach

          <label for="service-sort" class="sr-only">Sort services</label>
          <select id="service-sort" name="sort" onchange="this.form.submit()"
                  class="h-11 px-3 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:ring-2 focus:ring-[var(--brand)]">
            @foreach ($sorts as $sort)
              <option value="{{ $sort->value }}" @selected($filters->sort() === $sort)>{{ $sort->label() }}</option>
            @endforeach
          </select>
        </form>
      </div>
    @endif

    {{-- Quick filters survive into the results as removable chips, because the
         pills themselves are up in the header and out of sight by now. --}}
    @if ($filters->hasFilters())
      <div class="mb-8 flex flex-wrap items-center gap-2">
        @foreach ($filters->activeFilters() as $key => $value)
          @continue($key === 'sort')
          @php
            $label = match ($key) {
              'q' => '“'.$value.'”',
              'min' => 'from '.\App\Shared\Support\Money::format($filters->minKobo()),
              'max' => 'up to '.\App\Shared\Support\Money::format($filters->maxKobo()),
              'duration' => $filters->durationLabel(),
              'unit' => $filters->unit()?->label(),
              'featured' => 'Featured only',
              default => $key.': '.$value,
            };
          @endphp
          <a href="{{ $action.'?'.http_build_query(array_diff_key($filters->activeFilters(), [$key => null])) }}"
             class="t-meta inline-flex items-center gap-1.5 rounded-full border border-[var(--brand-edge)] px-3 py-1.5 hover:bg-[var(--brand-wash)]">
            {{ $label }} <span aria-hidden="true">&times;</span>
            <span class="sr-only">Remove filter</span>
          </a>
        @endforeach

        <a href="{{ $action }}" class="t-meta underline underline-offset-4 hover:decoration-2">Clear all</a>
      </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      @forelse ($services as $service)
        @include('themes.service.partials.service-card', [
            'service' => $service,
            'page' => $page,
            'cardCta' => 'Book Date',
        ])
      @empty
        <div class="col-span-full py-20 text-center">
          <p class="t-display-2">{{ $totalServices === 0 ? 'Nothing booked yet' : 'Nothing matched' }}</p>

          <p class="t-body mx-auto mt-3 max-w-sm text-[var(--ink-2)]">
            @if ($totalServices === 0)
              {{ $page->name }} has not listed any services yet.
            @else
              No service on this page matches
              @if ($filters->term())
                “{{ $filters->term() }}”
              @endif
              with the filters you picked. Try a longer time, or clear everything and start again.
            @endif
          </p>

          @if ($filters->hasFilters())
            <a href="{{ $action }}"
               class="mt-6 inline-flex items-center rounded-[var(--radius)] px-8 py-3.5 bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs hover:opacity-95 transition-opacity">
              Clear all filters
            </a>
          @endif
        </div>
      @endforelse
    </div>

    @if ($services->hasPages())
      <div class="mt-12">{{ $services->links() }}</div>
    @endif
  </div>
</x-themes.shared.layout>
