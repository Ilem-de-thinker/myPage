@props(['page', 'block'])

@php
    use App\Domains\Catalog\Models\Product;

    $heading = $block->field('heading');
    $body = $block->field('body');
    $image = $block->field('image') ?: $page->cover_path;
    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');

    /*
      The figures in the strip below are this shop's own numbers, counted here
      rather than typed: a seller's age, their catalogue size and what customers
      have said about them. Nothing on this page claims a figure nobody in the
      database can produce.
    */
    $catalogue = Product::query()->active()->where('store_page_id', $page->id)->count();
    $metrics = [];

    if ($page->published_at !== null) {
        $metrics[] = [(string) max(1, $page->published_at->diffInYears(now())), 'Years trading on mypage.ng'];
    }

    $metrics[] = [$catalogue.'+', 'Pieces in the catalogue'];

    if ($page->hasRating()) {
        $metrics[] = [number_format($page->ratingLabel(), 1).' ★', 'From '.$page->review_count.' verified '.\Illuminate\Support\Str::plural('review', $page->review_count)];
    }

    $metrics[] = [$page->locationLabel() ?: '—', 'Where to find us'];
@endphp

<section class="section-y relative overflow-hidden border-b border-[var(--rule)]">
  <div class="shell">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
      <div class="lg:col-span-7 space-y-6">
        @if ($where)
          <span class="inline-flex items-center gap-2 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold">
            <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
            {{ $where }}
          </span>
        @endif

        <h1 class="t-display-1 text-[var(--ink)]">{{ $heading ?: 'Our Story' }}</h1>

        @if ($page->tagline)
          <p class="t-body-lg text-[var(--ink-2)] t-measure">{{ $page->tagline }}</p>
        @endif

        {{-- Sanitised on save as well as here: the controller is the real gate. --}}
        @if ($body)
          <div class="t-body text-[var(--ink-2)] t-measure [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-[family-name:var(--font-heading-stack)] [&_h2]:text-xl [&_h2]:font-extrabold [&_li]:ml-5 [&_p]:mt-4">
            {!! \App\Shared\Support\Html::sanitize($body) !!}
          </div>
        @endif

        <div class="flex flex-wrap items-center gap-4 pt-2">
          <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
             class="inline-flex items-center gap-2 px-6 py-3 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-sm font-bold shadow-[var(--shadow-lift)] hover:opacity-95 transition-opacity">
            <span>Explore the Collection</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>

          <a href="{{ route('storefront.contact', ['slug' => $page->slug]) }}"
             class="inline-flex items-center gap-2 px-6 py-3 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-sm font-bold hover:bg-[var(--surface)] transition-colors">
            <span>Talk to the Studio</span>
          </a>
        </div>
      </div>

      <div class="lg:col-span-5">
        <div class="plate aspect-[4/5] rounded-[var(--radius-lg)] overflow-hidden shadow-[var(--shadow-lift)] border border-[var(--rule)] relative">
          @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt=""
                 width="600" height="750" loading="lazy"
                 class="w-full h-full object-cover">
          @endif
          <div class="grain" aria-hidden="true"></div>

          @if ($where)
            <div class="absolute bottom-4 left-4 right-4 p-4 rounded-[var(--radius)] bg-[var(--card)]/90 backdrop-blur-md border border-[var(--rule)] shadow-sm">
              <p class="text-xs font-bold text-[var(--ink)]">{{ $page->name }}</p>
              <p class="text-[11px] text-[var(--ink-3)]">{{ $where }}</p>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</section>

{{-- The numbers, from the rows rather than from a pitch. --}}
<section class="border-b border-[var(--rule)] bg-[var(--surface)] py-10">
  <div class="shell">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
      @foreach ($metrics as [$figure, $caption])
        <div class="space-y-1">
          <p class="t-price-lg text-[var(--brand-text)]">{{ $figure }}</p>
          <p class="t-meta">{{ $caption }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{--
  The seller's own account of themselves. The portrait is their logo or their
  hero photograph, the words are their bio, and the byline is their name — there
  is no second, invented person on this page.
--}}
@if ($page->bio)
  <section class="section-y bg-[var(--surface)] border-b border-[var(--rule)]">
    <div class="shell">
      <div class="max-w-4xl mx-auto rounded-[var(--radius-lg)] border border-[var(--rule)] bg-[var(--paper)] p-8 md:p-12 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
          <div class="md:col-span-4 text-center md:text-left">
            <div class="plate aspect-square w-36 h-36 md:w-48 md:h-48 mx-auto md:mx-0 rounded-[var(--radius-pill)] overflow-hidden border-2 border-[var(--brand-edge)] shadow-md">
              @if ($page->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}" alt=""
                     width="192" height="192" loading="lazy"
                     class="w-full h-full object-cover">
              @endif
              <div class="grain" aria-hidden="true"></div>
            </div>
            <h3 class="t-title text-[var(--ink)] mt-4">{{ $page->name }}</h3>
            <p class="t-meta">{{ $page->tagline ?: 'Seller on mypage.ng' }}</p>
          </div>

          <div class="md:col-span-8 space-y-4">
            <svg class="w-8 h-8 text-[var(--brand-text)] opacity-40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
            <p class="t-body text-[var(--ink-2)] italic leading-relaxed">{{ $page->bio }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
@endif

{{-- The platform's promise, which is the same promise on every shop. --}}
<section class="py-16">
  <div class="shell">
    <div class="rounded-[var(--radius-lg)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] p-8 md:p-10 flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="space-y-2">
        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--brand-text)]">
          <svg class="w-4 h-4 text-[var(--brand-text)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>100% Buyer Escrow Protection</span>
        </span>
        <h3 class="t-title text-[var(--ink)]">Every Naira Held In Vault Until You Confirm Delivery</h3>
        <p class="text-xs text-[var(--ink-2)] max-w-xl">
          {{ $page->name }} trades on mypage.ng sovereign escrow. When you place an order, your payment is held securely until you receive and inspect your parcel.
        </p>
      </div>

      <div class="flex items-center gap-4 shrink-0">
        <a href="{{ route('storefront.track-order', ['slug' => $page->slug]) }}"
           class="px-5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs font-bold hover:bg-[var(--surface)] transition-colors">
          How Escrow Protects You
        </a>
        <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
           class="px-5 py-2.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold shadow-sm hover:opacity-95 transition-opacity">
          Shop Catalogue
        </a>
      </div>
    </div>
  </div>
</section>