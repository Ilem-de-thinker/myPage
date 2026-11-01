@extends('layouts.platform')

@section('title', $heading.' — mypage.ng')
@section('description', 'Discover independent stores, products and services across Nigeria on mypage.ng, then deal directly with the seller.')
@section('canonical', route('directory.'.$kind))
@section('body-state', '{ mobileNavOpen: false, filtersOpen: false }')

@push('head')
@php
    $directoryItems = $kind === 'stores' ? [] : collect($items->items())->values()->map(function ($item, int $index) use ($kind): array {
        $page = $item->storePage;
        $isProduct = $kind === 'products';
        $url = $isProduct
            ? \App\Shared\Support\StoreUrl::absolute($page, 'storefront.product', ['productSlug' => $item->slug])
            : \App\Shared\Support\StoreUrl::absolute($page, 'storefront.service', ['serviceSlug' => $item->slug]);
        $entry = [
            '@type' => $isProduct ? 'Product' : 'Service',
            'name' => $item->name,
            'url' => $url,
            'provider' => ['@type' => 'Organization', 'name' => $page->name, 'url' => route('directory.store', $page)],
        ];
        if ($isProduct && !empty($item->primaryImageUrl)) {
            $entry['image'] = $item->primaryImageUrl;
        }
        if ($isProduct && $item->price_kobo !== null) {
            $entry['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'NGN', 'price' => number_format($item->price_kobo / 100, 2, '.', ''), 'url' => $url];
        }
        return ['@type' => 'ListItem', 'position' => $index + 1, 'item' => $entry];
    })->all();
@endphp
@if ($directoryItems !== [])
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $heading, 'itemListElement' => $directoryItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endpush

@section('content')
@php
    $totalCount = $items->total();
    $activeQuery = request('q');
    $activeState = request('state');
    $activeCity = request('city');
    $activeSort = request('sort');
    $hasActiveFilters = $activeQuery || $activeState || $activeCity || $activeSort || request('category') || request('min_price') || request('max_price') || request()->boolean('verified');
@endphp

<div class="min-h-screen bg-[var(--platform-paper)] text-[var(--platform-ink)] pb-24" x-data="{ loading: false }" @submit="loading = true">
  
  {{-- Ambient Tactile Layer --}}
  <x-platform.bg.grain />
  <x-platform.bg.spotlight />

  <div x-cloak x-show="loading" class="fixed inset-x-0 top-0 z-[60] h-1 overflow-hidden bg-[var(--platform-brand-wash)]" role="progressbar" aria-label="Loading results">
    <div class="h-full w-1/2 animate-pulse bg-[var(--platform-brand)]"></div>
  </div>

  {{-- Directory Header --}}
  <header class="platform-directory-header border-b border-[var(--platform-line)] bg-[var(--platform-surface)] pt-12 pb-10 sm:pt-16 sm:pb-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-8">
      <div data-reveal class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
        <div>
          <span class="platform-kicker">Nigerian Sovereign Directory</span>
          <h1 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-[var(--platform-ink)] sm:text-5xl lg:text-6xl">
            {{ $heading }}
          </h1>
          <p class="mt-4 max-w-2xl text-sm leading-relaxed text-[var(--platform-ink-muted)] sm:text-base">
            Discover verified makers and studio listings. When you find the right fit, we take you directly to the merchant’s sovereign store to ask, book, or order.
          </p>
        </div>

        {{-- Live Result Count --}}
        <div class="shrink-0 rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-5 py-3 shadow-sm">
          <span class="block text-[10px] font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Verified Listings</span>
          <span class="font-display text-2xl font-extrabold text-[var(--platform-brand)]" data-counter="{{ $totalCount }}">
            {{ number_format($totalCount) }}
          </span>
        </div>
      </div>

      {{-- Section Navigation Chips (Stores, Products, Services) --}}
      <nav class="mt-8 flex flex-wrap items-center gap-2 border-t border-[var(--platform-line)] pt-6" aria-label="Directory sections">
        <a href="{{ route('directory.stores') }}" class="btn-magnetic inline-flex h-10 items-center rounded-xl px-4 text-xs font-bold transition-all {{ $kind === 'stores' ? 'bg-[var(--platform-ink)] text-[var(--platform-ink-invert)] shadow-sm' : 'border border-[var(--platform-line)] bg-[var(--platform-card)] text-[var(--platform-ink)] hover:bg-[var(--platform-surface)]' }}">
          <span>Stores &amp; Studios</span>
        </a>
        <a href="{{ route('directory.products') }}" class="btn-magnetic inline-flex h-10 items-center rounded-xl px-4 text-xs font-bold transition-all {{ $kind === 'products' ? 'bg-[var(--platform-ink)] text-[var(--platform-ink-invert)] shadow-sm' : 'border border-[var(--platform-line)] bg-[var(--platform-card)] text-[var(--platform-ink)] hover:bg-[var(--platform-surface)]' }}">
          <span>Products</span>
        </a>
        <a href="{{ route('directory.services') }}" class="btn-magnetic inline-flex h-10 items-center rounded-xl px-4 text-xs font-bold transition-all {{ $kind === 'services' ? 'bg-[var(--platform-ink)] text-[var(--platform-ink-invert)] shadow-sm' : 'border border-[var(--platform-line)] bg-[var(--platform-card)] text-[var(--platform-ink)] hover:bg-[var(--platform-surface)]' }}">
          <span>Services &amp; Appointments</span>
        </a>

        <div class="ml-auto hidden sm:flex items-center gap-2">
          <button type="button" @click="$dispatch('marketplace-open')" class="btn-magnetic inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--platform-accent-border)] bg-[var(--platform-accent-wash)] px-4 text-xs font-bold text-[var(--platform-accent)] hover:bg-[var(--platform-accent)] hover:text-[var(--platform-ink-invert)] transition-all">
            <x-ui.icon name="spark" class="size-3.5" />
            <span>Need advice? “Help me buy”</span>
          </button>
        </div>
      </nav>
    </div>
  </header>

  {{-- Sticky Search & Filter Bar --}}
  <div class="platform-filter-bar sticky top-14 z-30 border-b border-[var(--platform-line)] bg-[var(--platform-paper)]/95 backdrop-blur-md py-4 transition-all">
    <div class="mx-auto max-w-7xl px-4 sm:px-8">
      <form method="GET" action="{{ $kind === 'stores' ? route('directory.stores') : route('directory.'.$kind) }}" class="flex items-center gap-3">
        
        {{-- Search Input with Elevated Focus --}}
        <div class="relative flex-1">
          <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-[var(--platform-ink-muted)]" />
          <input
            type="text"
            name="q"
            value="{{ $activeQuery }}"
            placeholder="Search within {{ $kind }} by title, craft, or city..."
            class="h-11 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] pl-10 pr-4 text-xs font-medium text-[var(--platform-ink)] placeholder:text-[var(--platform-ink-faint)] focus:border-[var(--platform-brand)] focus:ring-2 focus:ring-[var(--platform-brand)]/15 outline-none transition-all shadow-xs"
          >
        </div>

        {{-- Desktop Filters Dropdowns --}}
        <div class="hidden lg:flex items-center gap-2 shrink-0">
          <select name="state" class="h-11 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs font-semibold text-[var(--platform-ink)] outline-none focus:border-[var(--platform-brand)]">
            <option value="">All States</option>
            @foreach ($states as $state)
              <option value="{{ $state }}" @selected(request('state') === $state)>{{ $state }}</option>
            @endforeach
          </select>

          <select name="city" class="h-11 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs font-semibold text-[var(--platform-ink)] outline-none focus:border-[var(--platform-brand)]">
            <option value="">All Cities</option>
            @foreach ($cities as $city)
              <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
            @endforeach
          </select>

          <select name="sort" class="h-11 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs font-semibold text-[var(--platform-ink)] outline-none focus:border-[var(--platform-brand)]">
            <option value="">Best Match</option>
            <option value="price_low" @selected(request('sort') === 'price_low')>Price: Low to High</option>
            <option value="price_high" @selected(request('sort') === 'price_high')>Price: High to Low</option>
            <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
          </select>

          @if ($kind === 'products')
            <select name="category" class="h-11 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs font-semibold text-[var(--platform-ink)] outline-none focus:border-[var(--platform-brand)]">
              <option value="">All categories</option>
              @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
              @endforeach
            </select>
            <input name="min_price" value="{{ request('min_price') }}" type="number" min="0" placeholder="Min ₦" class="h-11 w-24 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs">
            <input name="max_price" value="{{ request('max_price') }}" type="number" min="0" placeholder="Max ₦" class="h-11 w-24 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs">
          @endif
          @if ($kind !== 'stores')
            <label class="flex h-11 items-center gap-2 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 text-xs font-semibold"><input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified')) class="accent-[var(--platform-brand)]"> Verified</label>
          @endif

          <button type="submit" class="btn-magnetic h-11 rounded-xl bg-[var(--platform-brand)] px-5 text-xs font-bold text-[var(--platform-ink-invert)] hover:bg-[var(--platform-brand-hover)] transition-all">
            Filter
          </button>
        </div>

        {{-- Mobile Filter Trigger Button --}}
        <button type="button" @click="filtersOpen = true" class="lg:hidden inline-flex h-11 items-center gap-2 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-4 text-xs font-bold text-[var(--platform-ink)]">
          <x-ui.icon name="filter" class="size-4" />
          <span>Filters</span>
          @if ($hasActiveFilters)
            <span class="size-2 rounded-full bg-[var(--platform-accent)]"></span>
          @endif
        </button>
      </form>

      {{-- Active Filter Pills --}}
      @if ($hasActiveFilters)
        <div class="mt-3 flex flex-wrap items-center gap-2 pt-2 border-t border-[var(--platform-line)] text-xs">
          <span class="font-bold text-[var(--platform-ink-muted)]">Active:</span>
          @if ($activeQuery)
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--platform-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--platform-ink)]">
              “{{ $activeQuery }}”
            </span>
          @endif
          @if ($activeState)
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--platform-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--platform-ink)]">
              State: {{ $activeState }}
            </span>
          @endif
          @if ($activeCity)
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--platform-surface)] px-2.5 py-1 text-[11px] font-semibold text-[var(--platform-ink)]">
              City: {{ $activeCity }}
            </span>
          @endif
          <a href="{{ route('directory.'.$kind) }}" class="ml-2 font-bold text-[var(--platform-accent)] hover:underline">
            Clear all
          </a>
        </div>
      @endif
    </div>
  </div>

  {{-- Main Listing Grid --}}
  <div class="mx-auto max-w-7xl px-4 sm:px-8 mt-10">
    @if ($items->isEmpty())
      {{-- Graceful Empty State with Illustrated Product Silhouette --}}
      <div data-reveal class="rounded-3xl border border-dashed border-[var(--platform-line)] bg-[var(--platform-card)] px-6 py-24 text-center">
        <div class="mx-auto size-20 rounded-2xl bg-[var(--platform-surface)] flex items-center justify-center text-[var(--platform-accent)]">
          <x-platform.visual.placeholder silhouette="vase" class="size-16 rounded-xl" />
        </div>
        <h2 class="mt-6 font-display text-2xl font-bold text-[var(--platform-ink)]">No published listings found</h2>
        <p class="mx-auto mt-2 max-w-md text-xs leading-relaxed text-[var(--platform-ink-muted)] sm:text-sm">
          We couldn’t find any verified listings matching your exact search. Try browsing all categories or ask “Help me buy” to find nearby alternatives.
        </p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
          <a href="{{ route('directory.'.$kind) }}" class="btn-magnetic inline-flex h-10 items-center rounded-xl bg-[var(--platform-ink)] px-5 text-xs font-bold text-[var(--platform-ink-invert)]">
            Reset search
          </a>
          <button type="button" @click="$dispatch('marketplace-open')" class="btn-magnetic inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--platform-line)] px-5 text-xs font-bold text-[var(--platform-brand)]">
            <x-ui.icon name="spark" class="size-3.5" />
            <span>Search with “Help me buy”</span>
          </button>
        </div>
      </div>
    @else
      <div data-stagger class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $idx => $item)
          @if ($kind === 'stores')
            <x-platform.cards.store-card :store="$item" :index="$idx" />
          @elseif ($kind === 'services')
            <x-platform.cards.service-card :service="$item" :index="$idx" />
          @else
            <x-platform.cards.product-card :product="$item" :index="$idx" />
          @endif
        @endforeach
      </div>

      {{-- Pagination --}}
      <div class="mt-14 border-t border-[var(--platform-line)] pt-8">
        {{ $items->links() }}
      </div>
    @endif
  </div>

  {{-- Mobile Filter Bottom Sheet with Drag Handle --}}
  <div x-show="filtersOpen" x-transition.opacity class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" style="display:none">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-xs" @click="filtersOpen = false"></div>
    <div x-show="filtersOpen" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0" class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-[var(--platform-card)] p-6 shadow-2xl">
      
      {{-- Drag Handle --}}
      <div class="sheet-drag-handle"></div>

      <div class="flex items-center justify-between border-b border-[var(--platform-line)] pb-4">
        <h3 class="font-display text-xl font-bold text-[var(--platform-ink)]">Filter Listings</h3>
        <button type="button" @click="filtersOpen = false" class="rounded-lg p-2 text-[var(--platform-ink-muted)] hover:bg-[var(--platform-surface)]">
          <x-ui.icon name="close" class="size-5" />
        </button>
      </div>

      <form method="GET" action="{{ $kind === 'stores' ? route('directory.stores') : route('directory.'.$kind) }}" class="mt-6 space-y-4">
        <input type="hidden" name="q" value="{{ $activeQuery }}">
        
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">State</label>
          <select name="state" class="mt-2 h-12 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm text-[var(--platform-ink)]">
            <option value="">All States</option>
            @foreach ($states as $state)
              <option value="{{ $state }}" @selected(request('state') === $state)>{{ $state }}</option>
            @endforeach
          </select>
        </div>

        @if ($kind === 'products')
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Category</label>
            <select name="category" class="mt-2 h-12 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm text-[var(--platform-ink)]">
              <option value="">All categories</option>
              @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label class="text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Min price<input name="min_price" value="{{ request('min_price') }}" type="number" min="0" placeholder="₦0" class="mt-2 h-12 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm"></label>
            <label class="text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Max price<input name="max_price" value="{{ request('max_price') }}" type="number" min="0" placeholder="Any" class="mt-2 h-12 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm"></label>
          </div>
        @endif
        @if ($kind !== 'stores')
          <label class="flex items-center gap-2 text-sm font-semibold text-[var(--platform-ink)]"><input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified')) class="size-4 accent-[var(--platform-brand)]"> Verified merchants only</label>
        @endif

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">City</label>
          <select name="city" class="mt-2 h-12 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm text-[var(--platform-ink)]">
            <option value="">All Cities</option>
            @foreach ($cities as $city)
              <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Sort Order</label>
          <select name="sort" class="mt-2 h-12 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm text-[var(--platform-ink)]">
            <option value="">Best Match</option>
            <option value="price_low" @selected(request('sort') === 'price_low')>Price: Low to High</option>
            <option value="price_high" @selected(request('sort') === 'price_high')>Price: High to Low</option>
            <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
          </select>
        </div>

        <div class="pt-4 flex gap-3">
          <a href="{{ route('directory.'.$kind) }}" class="inline-flex h-12 flex-1 items-center justify-center rounded-xl border border-[var(--platform-line)] text-xs font-bold text-[var(--platform-ink)]">
            Reset
          </a>
          <button type="submit" class="inline-flex h-12 flex-1 items-center justify-center rounded-xl bg-[var(--platform-brand)] text-xs font-bold text-[var(--platform-ink-invert)]">
            Apply Filters
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
