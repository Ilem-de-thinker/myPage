@props(['page', 'block'])

@php
    use App\Domains\Booking\Models\Service;

    // Real rows, not stored words: a service with no photo or description still
    // belongs on the page. Nothing here when the seller has not added one.
    $services = Service::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->orderBy('position')
        ->get();

    $heading = $block->field('heading');

    /*
     * Three cards, as the design sets them. Featured services lead because the
     * seller marked those themselves; the rest fill the row by position. A page
     // with four services shows three here and links to the full rate card.
     */
    $featured = $services->where('is_featured', true)->take(3);
    $showcased = $featured->count() > 0
        ? $featured->concat($services->reject(fn (Service $service) => $featured->contains('id', $service->id))->take(3 - $featured->count()))
        : $services->take(3);
@endphp

@if ($showcased->isNotEmpty())
  <section class="section-y border-b border-[var(--rule)]">
    <div class="shell">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
        <div>
          <span class="t-meta text-[var(--brand-text)] font-semibold">Curated Experiences</span>
          <h2 class="t-display-2 text-[var(--ink)] mt-1">{{ $heading ?: 'Signature Studio Offerings' }}</h2>
        </div>

        <a href="{{ route('storefront.services', ['slug' => $page->slug]) }}"
           class="text-xs font-bold text-[var(--brand-text)] hover:underline inline-flex items-center gap-1">
          <span>View Full Rate Card</span>
          <span aria-hidden="true">&rarr;</span>
        </a>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach ($showcased as $service)
          @include('themes.service.partials.service-card', [
              'service' => $service,
              'page' => $page,
              'cardCta' => 'Book Slot',
          ])
        @endforeach
      </div>

      @if ($services->count() > 3)
        <p class="mt-8 text-xs">
          <a href="{{ route('storefront.services', ['slug' => $page->slug]) }}"
             class="font-bold text-[var(--brand-text)] hover:underline inline-flex items-center gap-1">
            <span>See every service</span>
            <span aria-hidden="true">&rarr;</span>
          </a>
        </p>
      @endif
    </div>
  </section>
@endif