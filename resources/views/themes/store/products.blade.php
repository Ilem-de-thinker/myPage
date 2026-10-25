{{--
    The filtered page's own results, not the whole catalogue. Passing null made
    the layout fall back to every active product, so a category page described
    itself to search engines as selling items it was not showing.

    Two layouts, one panel. On a wide screen the filters are a sticky rail and the
    grid takes the rest; on a phone the rail becomes an off-canvas drawer and the
    grid takes the whole width. The panel itself is rendered from the same partial
    in both, so a filter can never behave differently depending on the viewport.
--}}
<x-themes.shared.layout :page="$page" :structured-products="$products->getCollection()">
  @php
    $total = $products->total();
    // One heading for the whole grid, even with several categories picked: naming
    // the first of three would describe only part of what is on screen.
    $headingCategory = $categories->firstWhere('id', $filters->categoryIds()[0] ?? null);
    // How many chips the row will show, which is also the number on the mobile
    // Filters button. Counted from the active filters rather than hardcoded, so
    // the badge cannot claim one filter when three are applied.
    $activeChips = $filters->hasFilters()
        ? count(array_filter(
            $filters->activeFilters(),
            fn ($value): bool => $value !== null && $value !== [] && $value !== '',
        ))
        : 0;
  @endphp

  <section class="shell pt-24 pb-16 md:pt-28">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 pb-6 mb-6 border-b border-[var(--rule)]">
      <div>
        <nav aria-label="Breadcrumb" class="mb-2">
          <ol class="flex items-center gap-2 text-xs text-[var(--ink-3)]">
            <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Home</a></li>
            <li aria-hidden="true">/</li>
            <li class="text-[var(--ink)] font-semibold" aria-current="page">Catalogue</li>
          </ol>
        </nav>

        <h1 class="t-display-2 text-[var(--ink)]">
          @if ($headingCategory)
            {{ $headingCategory->name }}
          @elseif ($filters->term())
            Results for “{{ $filters->term() }}”
          @else
            All products
          @endif
        </h1>

        {{-- The count in words, not just digits: "3 products" and "1 product"
             read differently, and a shopper uses the difference to tell whether
             they are at the end of a category or looking at a filtered view. --}}
        <p class="t-meta mt-1">
          Showing {{ $total }} {{ $total === 1 ? 'piece' : 'pieces' }}
          @if ($filters->hasFilters())
            <span class="text-[var(--ink-3)]">of {{ $totalProducts }} in this shop</span>
          @else
            from {{ $page->name }}
          @endif
        </p>
      </div>

      {{--
          Mobile: one button that opens the drawer. Above the breakpoint the
          rail is always visible and this button is display:none, so the two
          controls never appear at once.
      --}}
      <button type="button" x-data x-on:click="$dispatch('filters.open')"
              class="lg:hidden self-start sm:self-auto inline-flex items-center gap-2 px-4 py-2.5 rounded-[var(--radius)] bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] text-xs font-bold">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="4" x2="20" y1="21" y2="21"/><line x1="4" x2="20" y1="14" y2="14"/><line x1="4" x2="20" y1="7" y2="7"/><circle cx="14" cy="21" r="2"/><circle cx="8" cy="14" r="2"/><circle cx="16" cy="7" r="2"/></svg>
        <span>Filters</span>
        @if ($activeChips > 0)
          <span class="px-1.5 py-0.2 rounded-full bg-[var(--brand)] text-[var(--brand-ink)] text-[10px] tabular-nums">{{ $activeChips }}</span>
        @endif
      </button>
    </div>

    @if ($filters->hasFilters())
      {{--
          Active filters as chips, each one removable on its own.

          A chip row is the only place a buyer can see that a filter is applied
          after they have scrolled past the panel — and on a phone, where the
          panel is a drawer that is closed by default, it is the only place at all.
      --}}
      <div class="flex flex-wrap items-center gap-2 mb-8 text-xs">
        <span class="text-[var(--ink-3)] font-semibold">Active filters:</span>

        @if ($filters->term())
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] border border-[var(--rule)] text-[var(--ink)]">
            <span>“{{ $filters->term() }}”</span>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug] + array_diff_key($filters->activeFilters(), ['q' => null])) }}"
               class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1" aria-label="Remove search filter">×</a>
          </span>
        @endif

        @foreach ($filters->categoryIds() as $categoryId)
          @php $chip = $categories->firstWhere('id', $categoryId); @endphp
          @continue(! $chip)
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] border border-[var(--rule)] text-[var(--ink)]">
            <span>Category: {{ $chip->name }}</span>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug] + array_diff_key($filters->activeFilters(), ['category' => array_values(array_diff($filters->categoryIds(), [$categoryId]))])) }}"
               class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1" aria-label="Remove {{ $chip->name }} category filter">×</a>
          </span>
        @endforeach

        @foreach (['min' => 'From', 'max' => 'Up to'] as $key => $prefix)
          @if ($filters->input($key))
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] border border-[var(--rule)] text-[var(--ink)]">
              <span>{{ $prefix }} {{ \App\Shared\Support\Money::format($key === 'min' ? $filters->minKobo() : $filters->maxKobo()) }}</span>
              <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug] + array_diff_key($filters->activeFilters(), [$key => null])) }}"
                 class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1" aria-label="Remove {{ $prefix }} price filter">×</a>
            </span>
          @endif
        @endforeach

        @if ($filters->sort() !== \App\Domains\Catalog\Enums\ProductSort::Latest)
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] border border-[var(--rule)] text-[var(--ink)]">
            <span>{{ $filters->sort()->label() }}</span>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug] + array_diff_key($filters->activeFilters(), ['sort' => null])) }}"
               class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1" aria-label="Reset sorting">×</a>
          </span>
        @endif

        <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
           class="text-xs font-semibold text-[var(--brand-text)] hover:underline ml-2">
          Clear all filters
        </a>
      </div>
    @endif

    {{--
        The mobile drawer.

        Open state lives out here, on one wrapper, rather than inside the panel:
        the panel is rendered twice (rail and drawer) and the two copies must not
        be able to disagree about whether the drawer is open.
    --}}
    <div x-data="{ open: false }"
         x-on:filters.open.window="open = true"
         x-on:keydown.escape.window="open = false"
         class="lg:hidden">

      <div x-show="open" x-cloak
           x-transition:enter="transition-opacity duration-200"
           x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
           x-on:click="open = false"
           class="fixed inset-0 z-40"
           style="background: color-mix(in oklab, var(--ink) 40%, transparent)"></div>

      <div x-show="open" x-cloak
           x-transition:enter="transition-transform duration-300 ease-out"
           x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition-transform duration-200 ease-in"
           x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="fixed inset-0 z-50 flex justify-end bg-black/60 backdrop-blur-sm lg:hidden"
           role="dialog" aria-modal="true" aria-labelledby="mobile-filter-title">

        <div @click.away="open = false"
             class="w-full max-w-sm h-full bg-[var(--paper)] p-6 overflow-y-auto shadow-2xl flex flex-col justify-between">

          <div>
            <div class="flex items-center justify-between pb-4 border-b border-[var(--rule)] mb-6">
              <h2 id="mobile-filter-title" class="text-base font-bold text-[var(--ink)]">Filter Products</h2>
              <button type="button" @click="open = false" class="text-[var(--ink-3)] hover:text-[var(--ink)] text-sm font-bold p-1" aria-label="Close filter drawer">✕</button>
            </div>

            {{-- The same panel the rail draws, so a filter can never behave
                 differently depending on the viewport. --}}
            @include('themes.store.partials.catalog-filters', ['idPrefix' => 'drawer'])
          </div>

        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">

      {{-- The rail. Sticky below the navbar so it stays put while a long
           catalogue scrolls past, which is the whole reason for splitting the
           screen in two. --}}
      <aside class="hidden lg:col-span-1 lg:block" aria-label="Product filters">
        <div class="sticky top-20 max-h-[calc(100dvh-6rem)] overflow-y-auto p-5 rounded-[var(--radius-lg)] bg-[var(--surface)] border border-[var(--rule)]">
          @include('themes.store.partials.catalog-filters', ['idPrefix' => 'rail'])
        </div>
      </aside>

      <div class="lg:col-span-3">
        {{-- Two up on a phone is the most a thumb can compare; three from tablet
             up keeps the plates big enough to recognise a photograph. --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
          @forelse ($products as $product)
            @include('themes.store.partials.product-card', ['product' => $product, 'page' => $page])
          @empty
            {{--
                Two different failures with two different sentences. "Nothing
                matched" is the shopper's search and is fixable by them; "nothing
                listed yet" is the seller and no filter will change it.
            --}}
            <div class="col-span-full py-20 text-center">
              <p class="t-display-2">{{ $totalProducts === 0 ? 'Nothing listed yet' : 'Nothing matched' }}</p>

              <p class="t-body mx-auto mt-3 max-w-sm text-[color:var(--ink-2)]">
                @if ($totalProducts === 0)
                  {{ $page->name }} has not put any products up yet. Check back soon.
                @else
                  No products on this page match
                  @if ($filters->term())
                    “{{ $filters->term() }}”
                  @endif
                  @if ($filters->hasFilters())
                    with the filters you picked. Try a wider price range, or clear everything and start again.
                  @else
                    . Try another search.
                  @endif
                @endif
              </p>

              @if ($filters->hasFilters())
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
                   class="mt-6 inline-flex min-h-12 items-center rounded-[var(--radius)] px-7 text-[15px] font-bold"
                   style="background: var(--brand); color: var(--brand-ink)">
                  Clear all filters
                </a>
              @endif
            </div>
          @endforelse
        </div>

        {{-- Pagination carries the filter forward: the paginator was built with
             withQueryString(), so page two is the same filtered set, not the
             whole catalogue again. --}}
        @if ($products->hasPages())
          <div class="mt-10">{{ $products->links() }}</div>
        @endif
      </div>
    </div>
  </section>
</x-themes.shared.layout>