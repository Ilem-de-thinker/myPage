@props(['page', 'structuredProducts' => null])

@php
    use App\Domains\Seller\Enums\Theme;
    use App\Domains\Seller\Enums\StoreStatus;
    use App\Domains\Directory\Services\StructuredData;

    $isService = $page->theme === Theme::Service;

    // Only the home page puts a photograph behind the first screen, so only there
    // does the header start transparent instead of on a solid ground.
    $heroPhoto = request()->routeIs('storefront.show') && $page->heroImage();
@endphp
<!doctype html>
<html lang="en">
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
  <meta name="twitter:card" content="summary_large_image">

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
<body class="flex min-h-dvh flex-col antialiased selection:bg-[var(--brand-wash-2)] selection:text-[var(--brand-text)]"
      style="background: var(--paper); color: var(--ink); font-family: var(--font-body-stack)"
      data-store="{{ $page->slug }}"
      x-data="{ mobileNavOpen: false, scrolled: {{ $heroPhoto ? 'false' : 'true' }} }"
      @scroll.window="scrolled = window.pageYOffset > 24">

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
  <main id="main-content" class="flex-1 pt-16 pb-24 md:pb-12">
    {{ $slot }}
  </main>

  @if ($isService)
    <x-themes.service.whatsapp-bar :page="$page" />
    <x-themes.service.footer :page="$page" />
  @else
    <x-themes.store.whatsapp-bar :page="$page" />
    <x-themes.store.footer :page="$page" />
  @endif

</body>
</html>