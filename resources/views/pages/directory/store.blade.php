@extends('layouts.platform')

@section('title', $page->name.' — Discover on mypage.ng')
@section('description', $page->tagline ?: $page->bio)
@section('canonical', route('directory.store', $page))

@push('head')
@php
    $listingItems = $page->products->map(fn ($product, int $index): array => [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'item' => array_filter([
            '@type' => 'Product',
            'name' => $product->name,
            'description' => strip_tags((string) $product->description) ?: null,
            'url' => \App\Shared\Support\StoreUrl::absolute($page, 'storefront.product', ['productSlug' => $product->slug]),
            'image' => $product->primaryImageUrl ?: null,
            'offers' => $product->price_kobo !== null ? [
                '@type' => 'Offer',
                'priceCurrency' => 'NGN',
                'price' => number_format($product->price_kobo / 100, 2, '.', ''),
                'url' => \App\Shared\Support\StoreUrl::absolute($page, 'storefront.product', ['productSlug' => $product->slug]),
            ] : null,
        ]),
    ])->concat($page->services->map(fn ($service, int $index): array => [
        '@type' => 'ListItem',
        'position' => $page->products->count() + $index + 1,
        'item' => array_filter([
            '@type' => 'Service',
            'name' => $service->name,
            'description' => strip_tags((string) $service->description) ?: null,
            'url' => \App\Shared\Support\StoreUrl::absolute($page, 'storefront.service', ['serviceSlug' => $service->slug]),
        ]),
    ]))->values()->all();
@endphp
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $page->name,
    'description' => $page->tagline ?: $page->bio,
    'url' => route('directory.store', $page),
    'image' => $page->logo_path ? \Illuminate\Support\Facades\Storage::url($page->logo_path) : null,
    'address' => array_filter([
        '@type' => 'PostalAddress',
        'addressLocality' => $page->city,
        'addressRegion' => $page->state,
        'addressCountry' => 'NG'
    ]),
    'hasOfferCatalog' => $listingItems === [] ? null : [
        '@type' => 'OfferCatalog',
        'name' => $page->name.' catalogue',
        'itemListElement' => $listingItems,
    ],
], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
@php
    $goUrl = route('directory.go', ['type' => 'store', 'id' => $page->id, 'source' => 'store_profile']);
    $waUrl = route('directory.go', ['type' => 'whatsapp', 'id' => $page->id, 'source' => 'store_profile']);
    $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($page->name, 0, 2));
    $products = $page->products;
    $services = $page->services;

    $reportType = (string) request('report', 'store');
    $reportId = (int) request('id');

    if ($reportType === 'product' && ! $products->contains('id', $reportId)) {
        $reportType = 'store';
        $reportId = $page->id;
    }

    if ($reportType === 'service' && ! $services->contains('id', $reportId)) {
        $reportType = 'store';
        $reportId = $page->id;
    }

    if (! in_array($reportType, ['product', 'service'], true)) {
        $reportType = 'store';
        $reportId = $page->id;
    }
@endphp

<div class="min-h-screen bg-[var(--platform-paper)] text-[var(--platform-ink)] pb-32">
  
  {{-- Ambient Tactile Layer --}}
  <x-platform.bg.grain />
  <x-platform.bg.spotlight />

  {{-- Back Navigation --}}
  <div class="border-b border-[var(--platform-line)] bg-[var(--platform-paper)] py-3">
    <div class="mx-auto max-w-6xl px-4 sm:px-8">
      <a href="{{ route('directory.stores') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--platform-brand)] hover:underline">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>Back to all stores</span>
      </a>
    </div>
  </div>

  {{-- Cinematic Cover Header --}}
  <div class="platform-store-cover relative h-48 sm:h-64 lg:h-80 w-full overflow-hidden border-b border-[var(--platform-line)]">
    <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle, var(--platform-ink) 1px, transparent 1px); background-size: 20px 20px;"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-[var(--platform-paper)] via-transparent to-transparent opacity-80"></div>
  </div>

  <div class="mx-auto max-w-6xl px-4 sm:px-8 relative z-10 -mt-16 sm:-mt-20">
    
    {{-- Store Profile Card Header --}}
    <section data-reveal class="rounded-3xl border border-[var(--platform-line)] bg-[var(--platform-card)] p-6 sm:p-10 shadow-lg">
      <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8">
        
        {{-- Brand Monogram / Logo Overlap --}}
        <div class="flex items-start gap-5">
          <div class="flex size-20 sm:size-24 shrink-0 items-center justify-center rounded-3xl border-4 border-white bg-[var(--platform-paper)] shadow-md text-[var(--platform-brand)]">
            @if (!empty($page->logo_path))
              <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}" alt="{{ $page->name }}" class="size-full rounded-2xl object-cover">
            @else
              <span class="font-display text-3xl font-extrabold">{{ $initials }}</span>
            @endif
          </div>

          <div>
            <div class="flex flex-wrap items-center gap-2.5">
              <h1 class="font-display text-2xl sm:text-4xl font-extrabold tracking-tight text-[var(--platform-ink)]">
                {{ $page->name }}
              </h1>
              @if ($page->verified_at)
                <span class="inline-flex items-center gap-1 rounded-full bg-[var(--platform-brand-wash)] px-3 py-1 text-[10px] font-bold text-[var(--platform-brand)] border border-[var(--platform-brand-border)]">
                  <svg class="size-3 fill-current" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                  <span>Verified Merchant</span>
                </span>
              @endif
            </div>

            <p class="mt-2 text-xs font-semibold text-[var(--platform-ink-muted)] sm:text-sm">
              {{ $page->locationLabel() ?? ($page->city ? $page->city.', '.$page->state : 'Nigeria') }}
            </p>

            <p class="mt-4 max-w-2xl text-xs sm:text-sm leading-relaxed text-[var(--platform-ink-muted)]">
              {{ $page->bio ?: $page->tagline ?: 'Independent Nigerian merchant with direct ordering and live catalogue on their sovereign store.' }}
            </p>
          </div>
        </div>

        {{-- Direct Actions Desktop Panel --}}
        <div class="hidden lg:flex flex-col gap-3 shrink-0 w-72 rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-surface)] p-5">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--platform-accent)]">Direct Merchant Handoff</span>
          <p class="text-xs text-[var(--platform-ink-muted)] leading-relaxed">
            mypage.ng does not handle customer checkout. You buy and book directly with {{ $page->name }}.
          </p>

          <a href="{{ $goUrl }}" class="btn-magnetic mt-2 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--platform-brand)] px-5 text-xs font-extrabold text-white hover:bg-[var(--platform-brand-hover)] transition-all shadow-sm">
            <span>Visit {{ $page->name }}</span>
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>

          <a href="{{ $waUrl }}" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-card)] px-4 text-xs font-bold text-[var(--platform-brand)] hover:bg-[var(--platform-brand-wash)] transition-colors">
            <x-ui.icon name="whatsapp" class="size-4" />
            <span>Chat on WhatsApp</span>
          </a>
        </div>

      </div>
    </section>

    @if (session('report_sent'))
      <div class="mt-8 rounded-2xl border border-[var(--platform-brand-border)] bg-[var(--platform-brand-wash)] px-5 py-4 text-sm font-semibold text-[var(--platform-brand)]">
        {{ session('report_sent') }}
      </div>
    @endif

    {{-- Product Catalogue Preview --}}
    @if ($products->isNotEmpty())
      <section data-reveal class="mt-16">
        <div class="flex items-center justify-between border-b border-[var(--platform-line)] pb-4 mb-8">
          <div>
            <span class="platform-kicker">Store Offerings</span>
            <h2 class="mt-1 font-display text-2xl font-bold sm:text-3xl">Published Products</h2>
          </div>
          <a href="{{ $goUrl }}" class="link-draw text-xs font-bold text-[var(--platform-brand)]">View full shop catalogue ↗</a>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($products as $idx => $prod)
            <x-platform.cards.product-card :product="$prod" :index="$idx" />
          @endforeach
        </div>
      </section>
    @endif

    {{-- Services Catalogue Preview --}}
    @if ($services->isNotEmpty())
      <section data-reveal class="mt-16">
        <div class="flex items-center justify-between border-b border-[var(--platform-line)] pb-4 mb-8">
          <div>
            <span class="platform-kicker">Studio Bookings</span>
            <h2 class="mt-1 font-display text-2xl font-bold sm:text-3xl">Services &amp; Appointments</h2>
          </div>
          <a href="{{ $goUrl }}" class="link-draw text-xs font-bold text-[var(--platform-accent)]">Book on studio page ↗</a>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($services as $idx => $svc)
            <x-platform.cards.service-card :service="$svc" :index="$idx" />
          @endforeach
        </div>
      </section>
    @endif

    <details id="report" class="mt-16 rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-card)]" @if (request('report')) open @endif>
      <summary class="cursor-pointer list-none px-5 py-4 text-xs font-bold text-[var(--platform-ink-muted)] hover:text-[var(--platform-ink)]">
        Report a problem with this store or a listing
      </summary>
      <form method="POST" action="{{ route('directory.report', ['type' => $reportType, 'id' => $reportId]) }}" class="grid gap-4 border-t border-[var(--platform-line)] p-5 sm:grid-cols-2">
        @csrf
        <label class="text-xs font-bold text-[var(--platform-ink)]">
          What went wrong?
          <select name="category" required class="mt-2 h-11 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm font-normal">
            <option value="fraud">Fraud or scam</option>
            <option value="not_as_described">Not as described</option>
            <option value="misleading_listing">Misleading listing</option>
            <option value="other">Something else</option>
          </select>
        </label>
        <label class="text-xs font-bold text-[var(--platform-ink)]">
          Your contact (optional)
          <input type="text" name="reporter_contact" maxlength="190" class="mt-2 h-11 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm font-normal" placeholder="Email or phone">
        </label>
        <label class="text-xs font-bold text-[var(--platform-ink)] sm:col-span-2">
          What happened?
          <textarea name="body" required minlength="20" maxlength="2000" rows="4" class="mt-2 w-full rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 py-2 text-sm font-normal" placeholder="Give us enough detail to review this store. At least 20 characters."></textarea>
        </label>
        <input type="text" name="reporter_name" maxlength="120" class="h-11 rounded-xl border border-[var(--platform-line)] bg-[var(--platform-paper)] px-3 text-sm" placeholder="Your name (optional)">
        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-[var(--platform-ink)] px-5 text-xs font-bold text-white hover:bg-[var(--platform-accent)]">
          Send report
        </button>
      </form>
    </details>

  </div>

  {{-- Mobile Sticky Bottom Bar in Thumb Zone --}}
  <div class="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--platform-line)] bg-[var(--platform-card)]/95 backdrop-blur-md p-4 lg:hidden">
    <div class="flex items-center gap-3">
      <a href="{{ $waUrl }}" class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl border border-[var(--platform-line)] text-[var(--platform-brand)]">
        <x-ui.icon name="whatsapp" class="size-5" />
      </a>
      <a href="{{ $goUrl }}" class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-[var(--platform-brand)] px-5 text-sm font-extrabold text-white">
        <span>Visit {{ $page->name }}</span>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
    </div>
  </div>

</div>
@endsection
