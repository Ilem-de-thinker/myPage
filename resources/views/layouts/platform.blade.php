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
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'mypage.ng')</title>
  <meta name="description" content="@yield('description', 'Multi-tenant storefront platform for Nigerian businesses — products, services, bookings, and verified commerce.')">
  <meta property="og:title" content="@yield('og-title', 'mypage.ng — Sovereign Storefronts for Nigerian Enterprise')">
  <meta property="og:description" content="@yield('description', 'Multi-tenant storefront platform for Nigerian businesses — products, services, bookings, and verified commerce.')">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="canonical" href="{{ url()->current() }}">

  <!-- Typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

  {{-- Applied before first paint: a stored dark choice must never flash white. --}}
  <x-ui.theme-boot />

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[var(--paper)] text-[var(--ink)] min-h-screen selection:bg-[var(--brand-wash-2)] selection:text-[var(--brand-text)]"
      x-data="@yield('body-state', '{ mobileNavOpen: false }')">

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

</body>
</html>