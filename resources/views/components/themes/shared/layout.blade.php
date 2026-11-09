@props(['page', 'structuredProducts' => null])

@php
    use App\Domains\Seller\Enums\Theme;
    use App\Domains\Seller\Enums\StoreStatus;
    use App\Domains\Directory\Services\StructuredData;
    use App\Shared\Support\ThemeTokens;

    $isService = $page->theme === Theme::Service;

    // Only the home page puts a photograph behind the first screen, so only there
    // does the header start transparent instead of on a solid ground.
    $heroPhoto = request()->routeIs('storefront.show') && $page->heroImage();
    $pwaTokens = ThemeTokens::forPage($page)['vars'];
    $themePreset = $page->settings['theme_preset'] ?? ($isService ? 'minimal-swiss' : 'catalog');
    $ogImagePath = $page->og_image_path ?: $page->heroImage();
    $ogImage = $ogImagePath ? (str_starts_with($ogImagePath, 'http') ? $ogImagePath : \Illuminate\Support\Facades\Storage::url($ogImagePath)) : null;
@endphp
<!doctype html>
<html lang="en" data-theme-default="{{ $page->settings['surface'] ?? config('theming.defaults.surface') }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? $page->name }}</title>
  <meta name="description" content="{{ $description ?? $page->tagline }}">
  <link rel="canonical" href="{{ url()->current() }}">
  <meta property="og:title" content="{{ $page->name }}">
  <meta property="og:description" content="{{ $description ?? $page->tagline }}">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="mypage.ng">
  @if ($ogImage)
    <meta property="og:image" content="{{ url($ogImage) }}">
    <meta property="og:image:alt" content="{{ $page->name }} storefront image">
    <meta name="twitter:image" content="{{ url($ogImage) }}">
  @endif
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="{{ $pwaTokens['--brand'] }}">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="{{ $page->name }}">
  <link rel="manifest" href="{{ \App\Shared\Support\StoreUrl::path('storefront.pwa.manifest', ['slug' => $page->slug]) }}">
  <link rel="apple-touch-icon" href="{{ \App\Shared\Support\StoreUrl::path('storefront.pwa.icon', ['slug' => $page->slug, 'size' => 192]) }}">

  {{--
    Structured data. Only published pages: a draft page has no business being
    described to a search engine, and a suspended one must vanish from results.
    JSON encoded with the hex flags so a "</script>" inside a seller bio cannot
    break out of the tag.
  --}}
  @if ($page->status === StoreStatus::Published)
    <script type="application/ld+json">{!! json_encode(
        StructuredData::forPage($page, $structuredProducts),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) !!}</script>
  @endif
  {{--
    The seller's whole token set: colours, surfaces, radius dial and typeface.
    ThemeTokens derives every readable ink and OKLCH wash from the choices the
    seller made, so no template below has to decide what a colour looks like.
  --}}
  <x-theme-vars :page="$page" />
  <x-ui.theme-boot />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-motion="{{ $page->settings['motion_personality'] ?? ($isService ? 'boutique' : 'editorial') }}" data-reduce-motion="{{ ($page->settings['reduce_motion'] ?? false) ? 'true' : 'false' }}" class="flex min-h-dvh flex-col antialiased selection:bg-[var(--brand-wash-2)] selection:text-[var(--brand-text)]"
      style="background: var(--paper); color: var(--ink); font-family: var(--font-body-stack)"
      data-store="{{ $page->slug }}"
      data-theme-preset="{{ $themePreset }}"
      x-data="themeShell({ scrolled: {{ $heroPhoto ? 'false' : 'true' }} })">

  <x-themes.shared.animated-background :page="$page" />

  {{-- The skip link is the first focusable thing on every page. --}}
  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 z-50 px-4 py-2 bg-[var(--brand)] text-[var(--brand-ink)] font-semibold rounded-[var(--radius-sm)] shadow-[var(--shadow-lift)]">
    Skip to main content
  </a>

  @if ($isService)
    <x-themes.service.header :page="$page" />
  @else
    <x-themes.store.header :page="$page" />
  @endif

  {{--
    The page grows and the footer sits at the bottom of it. On a short page — a
    seller with two products and one review — the footer is still the last thing
    on screen rather than floating halfway down behind empty space.
  --}}
  <main id="main-content" data-reveal="fade" data-theme-motion="{{ $page->settings['motion_personality'] ?? ($isService ? 'boutique' : 'editorial') }}" class="flex-1 pt-16 pb-24 md:pb-12">
    @if (session('report_sent'))
      <div class="shell mb-6" role="status">
        <div class="rounded-[var(--radius)] border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-4 py-3 text-sm text-[var(--brand-text)]">{{ session('report_sent') }}</div>
      </div>
    @endif
    @if (session('support_sent'))
      <div class="shell mb-6" role="status">
        <div class="rounded-[var(--radius)] border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-4 py-3 text-sm text-[var(--brand-text)]">{{ session('support_sent') }}</div>
        @if (session('support_ticket_url'))
          <a href="{{ session('support_ticket_url') }}" class="mt-2 inline-flex text-xs font-bold text-[var(--brand-text)] underline">Open your support ticket</a>
        @endif
      </div>
    @endif
    @if ($storeReadOnly ?? false)
      <div class="shell mb-6" role="status">
        <div class="rounded-[var(--radius)] border border-[var(--warn-edge)] bg-[var(--warn-wash)] px-4 py-3 text-sm text-[var(--warn-text)]">
          <p class="font-semibold">{{ $page->name }} is temporarily not accepting new orders.</p>
          <p class="mt-1 text-xs">You can browse the catalogue, but checkout and bookings are paused. Please check back soon.</p>
        </div>
      </div>
    @endif
    {{ $slot }}
  </main>

  @if ($isService)
    @unless ($storeReadOnly ?? false)
      <x-themes.service.whatsapp-bar :page="$page" />
    @endunless
    <x-themes.service.footer :page="$page" />
  @else
    @unless ($storeReadOnly ?? false)
      <x-themes.store.whatsapp-bar :page="$page" />
    @endunless
    <x-themes.store.footer :page="$page" />
  @endif

  <x-support.floating-button :page="$page" />
  @if (($page->settings['ai_enabled'] ?? true) && app(\App\Domains\Ai\Services\AiSettings::class)->enabled())
    <x-ai.assistant :page="$page" />
    <x-ai.store-search :page="$page" />
  @endif
  <x-pwa.install-prompt :name="$page->name" :manifest="\App\Shared\Support\StoreUrl::path('storefront.pwa.manifest', ['slug' => $page->slug])" :worker="\App\Shared\Support\StoreUrl::path('storefront.pwa.worker', ['slug' => $page->slug])" />

</body>
</html>
