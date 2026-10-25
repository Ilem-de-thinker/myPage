@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $headline = $block->field('headline');
    $subtext = $block->field('subtext');
    // The cover photo is a page-wide setting, so it wins over a hero image. A
    // seller who set one should not have to set the other as well.
    $image = $page->heroImage();
    $button = $block->field('button_label') ?: 'Shop the collection';
    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your storefront.')
        : null;
    $location = $page->locationLabel();
    $slides = $page->carouselSlides()->where('is_active', true)->get();
    $heroStyle = $page->settings['hero_style'] ?? 'immersive';
    $heroAlignment = $page->settings['hero_alignment'] ?? 'center';
    $heroOverlay = $page->settings['hero_overlay'] ?? 'deep';
    $autoplay = (bool) ($page->settings['carousel_autoplay'] ?? false);
    $overlay = ['deep' => 'from-[var(--ink)]/90 via-[var(--ink)]/65 to-[var(--ink)]/40', 'soft' => 'from-[var(--ink)]/65 via-[var(--ink)]/35 to-transparent', 'none' => 'from-transparent via-transparent to-transparent'][$heroOverlay] ?? 'from-[var(--ink)]/85 via-[var(--ink)]/62 to-[var(--ink)]/45';
    $themePreset = $page->settings['theme_preset'] ?? 'catalog';
@endphp

{{--
  The photograph is the page, not a card beside it. Text sits on the image over
  a dark scrim, because a seller's phone photo has no way of knowing how light
  the words on it will be, and half of them are taken in bright daylight.

  The scrim is neutral ink rather than a brand tint: it has to darken a
  photograph of any colour without recolouring it, and it has to keep the
  headline readable whether the seller chose yellow or black.

  No cover and no hero image is a legitimate state, not a broken one: the
  headline simply takes the full measure on paper.
--}}
@if ($slides->isEmpty() && $themePreset === 'boutique-warm')
    <x-themes.store.blocks.home.hero-boutique :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'tech-pro')
    <x-themes.store.blocks.home.hero-tech :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'editorial-luxe')
    <x-themes.store.blocks.home.hero-editorial :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'minimal-swiss')
    <x-themes.store.blocks.home.hero-structured :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'brutalist-soft')
    <x-themes.store.blocks.home.hero-brutalist :page="$page" :block="$block" />
@elseif ($slides->isEmpty() && $themePreset === 'catalog')
    <x-themes.store.blocks.home.hero-catalog :page="$page" :block="$block" />
@elseif ($slides->isEmpty())
<section @class([
    'relative flex items-center overflow-hidden pt-16',
    'min-h-[85vh]' => $heroStyle === 'immersive',
    'min-h-[70vh]' => $heroStyle !== 'immersive',
    'justify-center' => $heroAlignment === 'center',
])>
  @if ($image)
    <div class="absolute inset-0 -z-20">
      <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
           alt=""
           class="w-full h-full object-cover"
           fetchpriority="high" decoding="async">

      {{-- Three stops: heaviest under the words, lightest at the top so the nav
           can stay transparent over it. --}}
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
    @if ($location || $page->isPublished())
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
        <span>{{ $location ? $location.' · ' : '' }}Verified Sovereign Store</span>
      </span>
    @endif

    @if ($headline)
      <h1 class="t-display-1 mb-6 {{ $image ? 'text-white' : '' }}">{{ $headline }}</h1>
    @endif

    @if ($subtext)
      <p @class([
          't-body-lg mb-8 t-measure mx-auto',
          'text-slate-200' => $image,
          'text-[var(--ink-2)]' => ! $image,
      ])>{{ $subtext }}</p>
    @endif

    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
         class="h-12 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-sm flex items-center justify-center hover:opacity-90 transition-opacity">
        {{ $button }} →
      </a>

      @if ($whatsapp)
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
           class="h-12 px-6 rounded-[var(--radius)] font-semibold text-sm flex items-center justify-center transition-colors {{ $image ? 'bg-white/15 backdrop-blur-md text-white border border-white/25 hover:bg-white/25' : 'bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--brand-wash)]' }}">
          Order on WhatsApp
        </a>
      @endif
    </div>
  </div>
</section>
@else
<section class="relative overflow-hidden bg-[var(--ink)] pt-16 text-white"
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
            <span class="mb-5 inline-flex rounded-[var(--radius-pill)] border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur-md">Featured from {{ $page->name }}</span>
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
    @foreach ($slides as $index => $slide)
      <span class="size-2 rounded-full bg-white/60"></span>
    @endforeach
  </div>
</section>
@endif

{{--
  The trust strip. Four standing promises every shop on the platform makes, so
  this is the platform's copy and not the seller's: it renders from the theme,
  with no block behind it, and none of it names a place, a price or a person.
--}}
@if ($page->settings['show_trust_strip'] ?? true)
<section class="border-y border-[var(--rule)] bg-[var(--surface)]">
  <div class="shell">
    <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-[var(--rule)] border-x border-[var(--rule)]">

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">01 / Authenticity</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Direct From The Seller</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">One shop, one conversation, no middlemen.</p>
      </div>

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">02 / Dispatch</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Tracked Delivery</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">A waybill reference on every order that leaves.</p>
      </div>

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">03 / Security</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Secure Payment</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">Payment goes straight to {{ $page->name }} at checkout.</p>
      </div>

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">04 / Concierge</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Direct WhatsApp</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">Ask a question and get an answer from a person.</p>
      </div>

    </div>
  </div>
</section>
@endif
