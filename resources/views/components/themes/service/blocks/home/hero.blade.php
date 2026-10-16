@props(['page', 'block'])

@php
    use App\Domains\Booking\Models\Service;
    use App\Domains\Booking\Models\StaffMember;

    $headline = $block->field('headline');
    $subtext = $block->field('subtext');
    // The cover photo is a page-wide setting, so it wins over a hero image.
    $image = $page->cover_path ?: $block->field('image');
    $where = $page->locationLabel();

    /*
     * The metrics strip under the hero.

     * The design's four figures were a fictional studio's brag sheet — brides
     * styled, chair time, an average score. None of those are columns, so they
     * are replaced with numbers this page can actually answer, and each one is
     * dropped rather than printed as a zero: "0 artists" is not a metric, it is
     * a page that has not been set up yet.
     *
     * @var \Illuminate\Support\Collection<int, array{0: string|int, 1: string}>
     */
    $serviceCount = Service::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->count();

    $staffCount = StaffMember::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->count();

    $metrics = collect([[$serviceCount, 'Services You Can Book']])
        ->when($staffCount > 0, fn ($metrics) => $metrics->push([$staffCount, 'Artists You Can Book With']))
        ->when($page->review_count > 0, fn ($metrics) => $metrics->push([number_format($page->review_count), 'Published Reviews']))
        ->when($page->hasRating(), fn ($metrics) => $metrics->push([number_format($page->ratingLabel(), 1).' ★', 'Verified Client Rating']))
        ->values()
        ->take(4);
@endphp

{{-- The photograph carries the page and the words sit on it over a neutral
     scrim, because a salon photo is either blown out midday light or a dim
     interior, and the headline has to survive both. --}}
<section class="relative flex items-center justify-center overflow-hidden @if ($image) min-h-[85vh] @endif">
  @if ($image)
    <div class="absolute inset-0 -z-20">
      <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
           alt=""
           class="w-full h-full object-cover"
           fetchpriority="high" decoding="async">

      <div class="absolute inset-0 bg-gradient-to-t from-[var(--ink)]/85 via-[var(--ink)]/62 to-[var(--ink)]/45"></div>
      <div class="grain" aria-hidden="true"></div>
    </div>
  @endif

  <div @class([
      'shell text-center py-20 relative z-10 max-w-4xl',
      'text-white' => $image,
  ])>
    <span @class([
        'inline-flex items-center gap-2 px-3 py-1 rounded-[var(--radius-pill)] text-xs font-semibold mb-6',
        'bg-white/10 backdrop-blur-md border border-white/20' => $image,
        'bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)]' => ! $image,
    ])>
      <span @class([
          'w-2 h-2 rounded-full',
          'bg-amber-400' => $image,
          'bg-[var(--brand)]' => ! $image,
      ]) aria-hidden="true"></span>

      {{-- Where the studio is, in the seller's own words. --}}
      {{ $where ?: $page->name }}
    </span>

    @if ($headline)
      <h1 @class([
          't-display-1 tracking-tight text-balance',
          'text-white' => $image,
          'text-[var(--ink)]' => ! $image,
      ])>{{ $headline }}</h1>
    @endif

    @if ($subtext)
      <p @class([
          't-body-lg max-w-2xl mx-auto mt-6 mb-8 t-measure',
          'text-white/90' => $image,
          'text-[var(--ink-2)]' => ! $image,
      ])>{{ $subtext }}</p>
    @endif

    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
      <a href="{{ route('storefront.book', ['slug' => $page->slug]) }}"
         class="w-full sm:w-auto h-12 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 shadow-[var(--shadow-lift)] hover:opacity-95 transition-opacity">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Check Calendar &amp; Book Slot</span>
      </a>

      <a href="{{ route('storefront.services', ['slug' => $page->slug]) }}"
         @class([
             'w-full sm:w-auto h-12 px-8 rounded-[var(--radius)] font-bold text-xs flex items-center justify-center transition-colors',
             'bg-white/10 text-white backdrop-blur-md border border-white/25 hover:bg-white/20' => $image,
             'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--surface)]' => ! $image,
         ])>
        <span>View Service Menu &amp; Rates</span>
      </a>
    </div>

    {{-- Trust badges. What the platform guarantees, not what this studio did. --}}
    <div @class([
        'mt-8 flex items-center justify-center gap-6 text-xs',
        'text-white/80' => $image,
        'text-[var(--ink-3)]' => ! $image,
    ])>
      <span>Live Availability</span>
      <span>·</span>
      <span>Zero Double-Booking</span>
      <span>·</span>
      <span>Protected Escrow Retainer</span>
    </div>
  </div>
</section>

{{--
    The studio metrics strip. Counted from rows rather than typed, and each figure
    only appears once there is something to count.
--}}
@if ($metrics->isNotEmpty())
  <section class="border-b border-[var(--rule)] bg-[var(--surface)] py-8">
    <div class="shell">
      <div @class([
          'grid gap-6 text-center',
          'grid-cols-2' => $metrics->count() === 2,
          'grid-cols-2 md:grid-cols-4' => $metrics->count() > 2,
      ])>
        @foreach ($metrics as [$figure, $caption])
          <div>
            <p class="t-price-lg text-[var(--brand-text)]">{{ $figure }}</p>
            <p class="t-meta">{{ $caption }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>
@endif