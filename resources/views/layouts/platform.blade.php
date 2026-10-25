{{--
    The platform shell.

    Every page that is about mypage.ng rather than about one seller renders
    through this: the landing page, discovery, the directory and the design
    system. The header and footer are components, so a page only ever supplies
    its own <main> contents and which nav item is current.

    A storefront is deliberately not this shell — `x-themes.shared.layout` is a
    separate document with the seller's tokens, their nav and their WhatsApp
    bar. One platform, two shells, neither nesting inside the other.
--}}
@php
    $demoStore = config('mypage.demo.store');
    $demoService = config('mypage.demo.service');

    // Which nav item is current. Derived from the route rather than passed down:
    // discovery and the directory are the only two pages on this shell that mark
    // themselves, and the state lives with the route that owns it.
    $active = match (true) {
        request()->routeIs('discovery.index') => 'discover',
        request()->routeIs('directory.*') => 'directory',
        default => null,
    };
@endphp
<!doctype html>
<html lang="en" data-theme-default="system">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'mypage.ng')</title>
  <meta name="description" content="@yield('description', 'Discover Nigerian stores, products and services, then connect directly with the merchant.')">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta property="og:title" content="@yield('og-title', 'mypage.ng — Sovereign Storefronts for Nigerian Enterprise')">
  <meta property="og:description" content="@yield('description', 'Discover Nigerian stores, products and services, then connect directly with the merchant.')">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="{{ config('platform.tokens.brand') }}">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="mypage.ng">
  <link rel="manifest" href="{{ route('pwa.manifest', [], false) }}">
  <link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 192], false) }}">
  <link rel="canonical" href="@yield('canonical', url()->current())">

  <!-- Typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">

  {{-- Applied before first paint: a stored dark choice must never flash white. --}}
  <x-ui.theme-boot />

  @stack('head')

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-platform-shell data-motion="{{ config('platform.animation.default_personality') }}" class="bg-[var(--platform-paper)] text-[var(--platform-ink)] min-h-screen selection:bg-[var(--platform-surface)] selection:text-[var(--platform-brand)]"
      x-data="@yield('body-state', '{ mobileNavOpen: false }')"
      x-on:marketplace-open.window="mobileNavOpen = false"
      x-on:support-open.window="mobileNavOpen = false">

  <div class="platform-scroll-progress" data-scroll-progress aria-hidden="true"></div>
  <x-platform.background-field :ghost="request()->routeIs('home') ? 'FIND IT. GO THERE.' : 'DISCOVER LOCAL.'" :route="request()->routeIs('home')" />

  @if (! request()->routeIs('home'))
    <div class="platform-global-motion-field" aria-hidden="true"><span></span><span></span><i></i></div>
  @endif

  {{-- The skip link is the first focusable thing on every page. --}}
  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 z-50 px-4 py-2 bg-[var(--brand)] text-[var(--brand-ink)] font-semibold rounded-[var(--radius-sm)] shadow-[var(--shadow-lift)]">
    Skip to main content
  </a>

  <x-platform.header
      :active="$active"
      :demo-store="$demoStore"
      :demo-service="$demoService" />

  <main id="main-content">
    @yield('content')
  </main>

  <x-platform.footer :demo-store="$demoStore" :demo-service="$demoService" />

  <x-platform.floating-stack />
  <x-support.floating-button :standalone="false" />
  <x-directory.help-me-buy />

  @if ($errors->any())
    <div role="alert" class="fixed right-4 bottom-4 z-50 max-w-sm rounded-[var(--radius)] border border-red-200 bg-white px-4 py-3 text-sm text-red-700 shadow-[var(--shadow-lift)]">
      <p class="font-semibold">Please review the information you entered.</p>
      <ul class="mt-1 list-disc pl-4 space-y-0.5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <x-pwa.install-prompt name="mypage.ng" :manifest="route('pwa.manifest', [], false)" :worker="route('pwa.worker', [], false)" />

</body>
</html>
