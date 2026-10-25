@props(['page', 'block'])

@php
    use App\Domains\Booking\Models\Service;
    use App\Domains\Booking\Models\StaffMember;

    $headline = $block->field('headline');
    $subtext = $block->field('subtext');
    // The cover photo is a page-wide setting, so it wins over a hero image.
    $image = $page->cover_path ?: $block->field('image');
    $where = $page->locationLabel();
    $slides = $page->carouselSlides()->where('is_active', true)->get();
    $heroStyle = $page->settings['hero_style'] ?? 'immersive';
    $heroAlignment = $page->settings['hero_alignment'] ?? 'center';
    $heroOverlay = $page->settings['hero_overlay'] ?? 'deep';
    $autoplay = (bool) ($page->settings['carousel_autoplay'] ?? false);
    $overlay = ['deep' => 'from-[var(--ink)]/90 via-[var(--ink)]/65 to-[var(--ink)]/40', 'soft' => 'from-[var(--ink)]/65 via-[var(--ink)]/35 to-transparent', 'none' => 'from-transparent via-transparent to-transparent'][$heroOverlay] ?? 'from-[var(--ink)]/85 via-[var(--ink)]/62 to-[var(--ink)]/45';
    $themePreset = $page->settings['theme_preset'] ?? 'minimal-swiss';

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
@if ($slides->isEmpty() && $themePreset === 'boutique-warm')
<x-themes.service.blocks.home.hero-boutique :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'tech-pro')
<x-themes.service.blocks.home.hero-tech :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'editorial-luxe')
<x-themes.service.blocks.home.hero-editorial :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'minimal-swiss')
<x-themes.service.blocks.home.hero-structured :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'brutalist-soft')
<x-themes.service.blocks.home.hero-brutalist :page="$page" :block="$block" />
@elseif ($slides->isEmpty())
<section @class([
    'relative flex items-center overflow-hidden pt-0',
    'min-h-[85vh]' => $heroStyle === 'immersive' && $image,
    'min-h-[70vh]' => $heroStyle !== 'immersive' || ! $image,
    'justify-center' => $heroAlignment === 'center',
])>
  @if ($image)
    <div class="absolute inset-0 -z-20">
      <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
           alt=""
           class="w-full h-full object-cover"
           fetchpriority="high" decoding="async">

      <div class="absolute inset-0 bg-gradient-to-t {{ $overlay }}"></div>
      <div class="grain" aria-hidden="true"></div>
    </div>
  @endif

  <div @class([
      'shell py-20 relative z-10 max-w-4xl',
      'text-center' => $heroAlignment === 'center',
      'text-left' => $heroAlignment === 'left',
      'text-white' => $image,
  ])>
    <span @class([
        'inline-flex items-center gap-2 px-3 py-1 rounded-[var(--radius-pill)] text-xs font-semibold mb-6',
        'bg-white/10 backdrop-blur-md border border-white/20' => $image,
        'bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)]' => ! $image,
    ])>
      <span @class([
          'w-2 h-2 rounded-full',
          'bg-[var(--brand)]' => $image,
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
      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', ['slug' => $page->slug]) }}"
         class="w-full sm:w-auto h-12 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 shadow-[var(--shadow-lift)] hover:opacity-95 transition-opacity">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Check Calendar &amp; Book Slot</span>
      </a>

      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.services', ['slug' => $page->slug]) }}"
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
      <span>Secure Deposit</span>
    </div>
  </div>
</section>
@else
<section class="relative overflow-hidden bg-[var(--ink)]"
         x-data="{ activeSlide: 0 }"
         @if ($autoplay) x-init="setInterval(() => { activeSlide = (activeSlide + 1) % {{ $slides->count() }}; $refs.track.children[activeSlide].scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 7000)" @endif>
  <div x-ref="track" class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth">
    @foreach ($slides as $index => $slide)
      <article class="relative min-w-full snap-start">
        @if ($slide->image_path)
          <img src="{{ $slide->imageUrl() }}" alt="" class="absolute inset-0 h-full min-h-[70vh] w-full object-cover" loading="{{ $index === 0 ? 'eager' : 'lazy' }}">
          <div class="absolute inset-0 bg-gradient-to-t {{ $overlay }}"></div>
        @endif
        <div class="shell relative flex min-h-[70vh] items-end py-24 {{ $heroAlignment === 'center' ? 'justify-center text-center' : 'justify-start text-left' }}">
          <div class="max-w-3xl">
            <span class="mb-5 inline-flex rounded-[var(--radius-pill)] border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur-md">A new chapter at {{ $page->name }}</span>
            <h1 class="t-display-1 text-white">{{ $slide->headline }}</h1>
            @if ($slide->subtext)<p class="t-body-lg mt-5 max-w-2xl text-white/85">{{ $slide->subtext }}</p>@endif
            @if ($slide->button_label && $slide->button_url)
              <a href="{{ $slide->button_url }}" class="mt-8 inline-flex h-12 items-center rounded-[var(--radius)] bg-[var(--brand)] px-8 text-sm font-bold text-[var(--brand-ink)]">{{ $slide->button_label }}</a>
            @endif
          </div>
        </div>
      </article>
    @endforeach
  </div>
  <div class="pointer-events-none absolute inset-x-0 bottom-5 flex justify-center gap-2">
    @foreach ($slides as $slide)
      <span class="size-2 rounded-full bg-white/60"></span>
    @endforeach
  </div>
</section>
@endif

{{--
    The studio metrics strip. Counted from rows rather than typed, and each figure
    only appears once there is something to count.
--}}
@if (($page->settings['show_metrics'] ?? true) && $metrics->isNotEmpty())
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
