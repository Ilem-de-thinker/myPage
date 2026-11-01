@extends('layouts.platform')

@section('title', 'MyPage.ng — Sovereign Storefronts & Discovery for Nigerian Commerce')
@section('description', 'Discover independent Nigerian stores, products, and services, then connect directly with the merchant. No marketplace cuts.')
@section('body-state', '{ mobileNavOpen: false }')

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'mypage.ng',
    'url' => route('home'),
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => route('directory.products').'?q={search_term_string}',
        'query-input' => 'required name=search_term_string'
    ]
], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="relative text-[var(--platform-ink)]">
  
  {{-- ==========================================
       SECTION 1: HERO
       ========================================== --}}
  <section class="relative min-h-[90vh] border-b border-[var(--platform-line)] pt-12 pb-20 sm:pt-20 sm:pb-32 lg:pt-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-8 relative z-10">
      <div class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
        
        {{-- Hero Left: Headlines & Dominant Elevated Search --}}
        <div>
          <div data-reveal class="inline-flex items-center gap-2 rounded-full border border-[var(--platform-accent-border)] bg-[var(--platform-accent-wash)] px-3.5 py-1 text-xs font-bold uppercase tracking-widest text-[var(--platform-accent)]">
            <span>The Nigerian Discovery Desk</span>
          </div>

          {{-- Masked Line Reveal Headline --}}
          <div class="mt-6" data-reveal="mask-line">
            <h1 class="font-display text-[clamp(2.75rem,7vw,5.8rem)] font-extrabold leading-[0.92] tracking-[-0.055em] text-[var(--platform-ink)]">
              Find the place <br>
              <em class="font-normal italic text-[var(--platform-brand)]">that gets it.</em>
            </h1>
          </div>

          <p data-reveal style="--stagger-index: 1" class="mt-6 max-w-xl text-base leading-relaxed text-[var(--platform-ink-muted)] sm:text-lg">
            A considered way to discover independent Nigerian stores, makers, and studios. Search the network, examine real details, then continue straight to the seller's sovereign store.
          </p>

          {{-- Dominant Search Bar elevating on focus --}}
          <div data-reveal style="--stagger-index: 2" class="mt-10 max-w-2xl">
            <form action="{{ route('directory.products') }}" method="GET" class="platform-hero-search flex flex-col gap-2 p-2 sm:flex-row sm:items-center">
              <div class="flex flex-1 items-center gap-3 px-3">
                <x-ui.icon name="search" class="size-5 shrink-0 text-[var(--platform-brand)]" />
                <input
                  type="text"
                  name="q"
                  data-rotating-search
                  placeholder="“birthday cake in Abuja”"
                  class="w-full border-0 bg-transparent py-2.5 text-sm font-medium text-[var(--platform-ink)] outline-none placeholder:text-[var(--platform-ink-faint)] focus:ring-0"
                >
              </div>

              <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('marketplace-open')" class="btn-magnetic inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-surface)] px-4 text-xs font-bold text-[var(--platform-ink)] hover:bg-[var(--platform-surface-hover)] transition-colors">
                  <x-ui.icon name="spark" class="size-3.5 text-[var(--platform-accent)]" />
                  <span>Help me buy</span>
                </button>
                <button type="submit" class="btn-magnetic inline-flex h-11 items-center justify-center rounded-xl bg-[var(--platform-brand)] px-6 text-xs font-extrabold text-[var(--platform-ink-invert)] hover:bg-[var(--platform-brand-hover)] transition-all">
                  <span>Search</span>
                </button>
              </div>
            </form>

            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-[var(--platform-ink-muted)]">
              <span class="font-semibold text-[var(--platform-ink)]">Trending:</span>
              <a href="{{ route('directory.products', ['q' => 'Akwete']) }}" class="link-draw hover:text-[var(--platform-brand)]">Akwete fabrics</a>
              <span>·</span>
              <a href="{{ route('directory.products', ['q' => 'Ceramics']) }}" class="link-draw hover:text-[var(--platform-brand)]">Ceramics</a>
              <span>·</span>
              <a href="{{ route('directory.services', ['q' => 'Bridal']) }}" class="link-draw hover:text-[var(--platform-brand)]">Bridal styling</a>
              <span>·</span>
              <a href="{{ route('directory.products', ['q' => 'Leather']) }}" class="link-draw hover:text-[var(--platform-brand)]">Leather goods</a>
            </div>
          </div>

          <div data-reveal style="--stagger-index: 3" class="mt-8 flex items-center gap-6 border-t border-[var(--platform-line)] pt-6 text-xs text-[var(--platform-ink-muted)]">
            <span class="flex items-center gap-2">
              <svg class="size-4 text-[var(--platform-brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Zero buyer accounts</span>
            </span>
            <span class="flex items-center gap-2">
              <svg class="size-4 text-[var(--platform-brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Zero marketplace commission</span>
            </span>
            <span class="flex items-center gap-2">
              <svg class="size-4 text-[var(--platform-brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              <span>Direct seller payment</span>
            </span>
          </div>
        </div>

        {{-- Hero Right: the animated Mercho storefront illustration. --}}
        <div class="relative flex min-h-[380px] items-center justify-center sm:min-h-[480px] lg:min-h-[560px]">
          <x-platform.mercho-hero class="max-w-[640px]" />
        </div>

      </div>
    </div>
  </section>

  {{-- Slow Marquee Ticker (Categories, Cities, Merchant Names) --}}
  <div class="platform-marquee-wrap border-b border-[var(--platform-line)] bg-[var(--platform-surface)] py-3.5" data-pause-motion aria-label="Directory highlights">
    <div class="platform-marquee-inner text-xs font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">
      @foreach ($cities as $c)
        <span class="flex items-center gap-4 px-6 whitespace-nowrap">
          <span>{{ is_object($c) ? $c->city : $c }}</span>
          <span class="size-1.5 rounded-full bg-[var(--platform-accent)]"></span>
        </span>
      @endforeach
      @foreach ($categories as $cat)
        @php
          $catName = is_array($cat) ? $cat['name'] : $cat->name;
        @endphp
        <span class="flex items-center gap-4 px-6 whitespace-nowrap">
          <span>{{ $catName }}</span>
          <span class="size-1.5 rounded-full bg-[var(--platform-brand)]"></span>
        </span>
      @endforeach
      <span class="flex items-center gap-4 px-6 whitespace-nowrap">
        <span>Direct WhatsApp Orders</span>
        <span class="size-1.5 rounded-full bg-[var(--platform-accent)]"></span>
      </span>
      <span class="flex items-center gap-4 px-6 whitespace-nowrap">
        <span>Zero Marketplace Cuts</span>
        <span class="size-1.5 rounded-full bg-[var(--platform-brand)]"></span>
      </span>
    </div>
  </div>

  {{-- ==========================================
       SECTION 2: CATEGORY STRIP / RAIL
       ========================================== --}}
  @if ($categories->isNotEmpty())
    <section class="border-b border-[var(--platform-line)] bg-[var(--platform-paper)] py-16 lg:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-8">
        <div data-reveal class="flex flex-wrap items-end justify-between gap-4 mb-8">
          <div>
            <span class="platform-kicker">Curated Domains</span>
            <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Browse by Nigerian Discipline</h2>
          </div>
          <a href="{{ route('directory.products') }}" class="link-draw text-xs font-bold text-[var(--platform-brand)]">View all directory categories ↗</a>
        </div>

        {{-- Mobile: Swipeable snap rail / Desktop: Hover-expanding strip --}}
        <div class="category-rail-container lg:category-strip gap-3 pb-4">
          @foreach ($categories->take(6) as $cat)
            @php
              $catName = is_array($cat) ? $cat['name'] : $cat->name;
              $catCount = is_array($cat) ? ($cat['count'].' items') : (($cat->live_products_count ?? '').' items');
            @endphp
            <a href="{{ route('directory.category', $catName) }}" class="category-rail-item category-strip-tile w-[260px] sm:w-[280px] lg:w-auto rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-card)] p-5 transition-all hover:border-[var(--platform-brand)] hover:shadow-md flex flex-col justify-between min-h-[200px] group">
              <div class="flex items-start justify-between">
                <span class="rounded-full bg-[var(--platform-surface)] px-2.5 py-1 text-[10px] font-bold text-[var(--platform-ink-muted)]">
                  {{ $catCount }}
                </span>
                <svg class="size-4 text-[var(--platform-ink-muted)] group-hover:text-[var(--platform-brand)] transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </div>

              <div>
                <x-platform.visual.placeholder silhouette="garment" class="aspect-[16/9] w-full rounded-xl my-3" />
                <h3 class="font-display text-lg font-bold text-[var(--platform-ink)] group-hover:text-[var(--platform-brand)] transition-colors line-clamp-1">
                  {{ $catName }}
                </h3>
                <p class="mt-1 text-[11px] text-[var(--platform-ink-muted)] truncate">Direct merchant catalogue</p>
              </div>
            </a>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  {{-- ==========================================
       SECTION 3: HELP ME BUY SHOWCASE
       ========================================== --}}
  <x-platform.sections.help-me-buy-showcase :products="$products" :services="$services" />

  {{-- ==========================================
       SECTION 4: TRENDING PRODUCTS & TOP SERVICES (Mixed Grid)
       ========================================== --}}
  <section class="border-b border-[var(--platform-line)] bg-[var(--platform-paper)] py-20 lg:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-8">
      
      <div data-reveal class="flex flex-wrap items-end justify-between gap-6 mb-12">
        <div class="max-w-2xl">
          <span class="platform-kicker">Published Listings</span>
          <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight sm:text-5xl">
            Trending creations &amp; studio services.
          </h2>
          <p class="mt-3 text-sm text-[var(--platform-ink-muted)] sm:text-base">
            Every item links straight to the merchant's dedicated shop. Confirm availability, book dates, or order through WhatsApp.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <a href="{{ route('directory.products') }}" class="btn-magnetic rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-4 py-2 text-xs font-bold text-[var(--platform-ink)] hover:bg-[var(--platform-surface)] transition-colors">
            All Products ↗
          </a>
          <a href="{{ route('directory.services') }}" class="btn-magnetic rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-4 py-2 text-xs font-bold text-[var(--platform-ink)] hover:bg-[var(--platform-surface)] transition-colors">
            All Services ↗
          </a>
        </div>
      </div>

      {{-- Designed Mixed-Size Grid --}}
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($products->take(3) as $idx => $prod)
          <x-platform.cards.product-card :product="$prod" :index="$idx" />
        @endforeach

        @foreach ($services->take(2) as $idx => $svc)
          <x-platform.cards.service-card :service="$svc" :index="$idx + 3" />
        @endforeach

        {{-- Special Editorial Callout Tile inside Grid --}}
        <div data-reveal style="--stagger-index: 5" class="rounded-3xl border border-[var(--platform-accent-border)] bg-[var(--platform-accent-wash)] p-8 flex flex-col justify-between">
          <div>
            <span class="rounded-full bg-[var(--platform-accent)] text-[var(--platform-ink-invert)] px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider">
              Network Standard
            </span>
            <h3 class="mt-4 font-display text-2xl font-bold text-[var(--platform-ink)]">
              Direct-to-Seller Commerce
            </h3>
            <p class="mt-3 text-xs leading-relaxed text-[var(--platform-ink-muted)] sm:text-sm">
              We never mask merchant phone numbers, withhold funds, or push buyer fees. You always deal directly with the hands that make your goods.
            </p>
          </div>

          <div class="mt-8 pt-6 border-t border-[var(--platform-accent-border)] flex items-center justify-between">
            <span class="text-xs font-bold text-[var(--platform-accent)]">Need assistance?</span>
            <button type="button" @click="$dispatch('marketplace-open')" class="btn-magnetic inline-flex items-center gap-1.5 text-xs font-extrabold text-[var(--platform-ink)] hover:underline">
              <span>Ask Help me buy</span>
              <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>
      </div>

    </div>
  </section>

  {{-- ==========================================
       SECTION 5: FEATURED & VERIFIED STORES (Oversized Cards)
       ========================================== --}}
  @if ($stores->isNotEmpty())
    <section class="border-b border-[var(--platform-line)] bg-[var(--platform-surface)] py-20 lg:py-32">
      <div class="mx-auto max-w-7xl px-4 sm:px-8">
        
        <div data-reveal class="flex flex-wrap items-end justify-between gap-6 mb-12">
          <div class="max-w-2xl">
            <span class="platform-kicker">Featured storefronts</span>
            <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight sm:text-5xl">
              Independent storefronts worth visiting.
            </h2>
            <p class="mt-3 text-sm text-[var(--platform-ink-muted)] sm:text-base">
              Nigerian makers, ateliers, studios and workshops with a published store you can explore directly.
            </p>
          </div>

          <a href="{{ route('directory.stores') }}" class="btn-magnetic rounded-xl bg-[var(--platform-ink)] px-5 py-2.5 text-xs font-bold text-[var(--platform-ink-invert)] hover:bg-[var(--platform-ink-subtle)] transition-colors">
            See all storefronts ↗
          </a>
        </div>

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($stores->take(3) as $idx => $store)
            <x-platform.cards.store-card :store="$store" :index="$idx" source="landing" />
          @endforeach
        </div>

      </div>
    </section>
  @endif

  {{-- ==========================================
       SECTION 6: HOW IT WORKS (Sticky Storytelling)
       ========================================== --}}
  <x-platform.sections.how-it-works-sticky :product="$products->first()" :store="$stores->first()" />

  {{-- ==========================================
       SECTION 7: FOR MERCHANTS (Dark Contrasting Band)
       ========================================== --}}
  <x-platform.sections.for-merchants :store="$stores->first()" />

  {{-- ==========================================
       SECTION 8: TRUST, TESTIMONIALS & FAQ & FINAL CTA
       ========================================== --}}
  <x-platform.sections.testimonials-and-faq :reviews="$reviews" />

</div>
@endsection
