@props([
    'active' => null,
    'demoStore' => null,
    'demoService' => null,
])

@php
    use App\Domains\Admin\Models\PlatformSetting;
    use App\Domains\Billing\Services\Feature;

    // Discovery is optional; the public directory is the platform's primary surface.
    $showDiscovery = Feature::enabled('discovery');
    $showDirectory = Feature::enabled('directory');

    $platformName = PlatformSetting::value('platform_name', 'mypage.ng');
    $platformLogo = PlatformSetting::value('platform_logo_path');
@endphp

<header class="platform-shell-header sticky top-0 z-40 h-14 border-b transition-all">
    <div class="shell h-full flex items-center justify-between gap-6">
      <!-- Zone 1: Wordmark -->
      <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-extrabold tracking-tight text-[var(--ink)] whitespace-nowrap shrink-0">
        @if ($platformLogo)
          <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformLogo) }}" alt="" class="h-7 w-7 rounded-[var(--radius-sm)] object-cover">
        @endif
        <span style="font-family: var(--font-heading)">{{ $platformName }}</span>
      </a>

      <!-- Zone 2: Navigation Links -->
      <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-[var(--ink-2)]" aria-label="Main platform navigation">
        @if ($showDiscovery)
          <a href="{{ route('discovery.index') }}" @class([
            'whitespace-nowrap',
            'text-[var(--brand-text)] font-semibold' => $active === 'discover',
            'hover:text-[var(--ink)] transition-colors' => $active !== 'discover',
          ])>Discover</a>
        @endif
        @if ($showDirectory)
          <a href="{{ route('directory.index') }}" @class([
            'whitespace-nowrap',
            'text-[var(--brand-text)] font-semibold' => $active === 'directory',
            'hover:text-[var(--ink)] transition-colors' => $active !== 'directory',
          ])>Directory</a>
        @endif
        <a href="{{ route('home') }}#discover" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Browse</a>
        <a href="{{ route('home') }}#how-it-works" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">How it Works</a>
        <a href="{{ route('home') }}#pricing" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Pricing</a>
        <a href="{{ route('design-system') }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Design System</a>
      </nav>

      <!-- Zone 3: Primary Action & Live Demos -->
      <div class="flex items-center gap-2.5 shrink-0">
        {{-- Platform pages carry no seller surface mode, so this falls back to
             the design system's own default and the OS preference. --}}
        <x-ui.theme-toggle />

        @if ($demoStore)
          <a href="{{ \App\Shared\Support\StoreUrl::homeForSlug($demoStore) }}" class="hidden sm:inline-flex items-center justify-center px-3 py-1.5 text-xs font-bold rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] hover:bg-[var(--brand-wash-2)] transition-colors whitespace-nowrap">
          <x-ui.icon name="storefront" class="mr-1.5 size-3.5" /> <span>Store Demo</span>
          </a>
        @endif
        @if ($demoService)
          <a href="{{ \App\Shared\Support\StoreUrl::homeForSlug($demoService) }}" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-bold rounded-[var(--radius)] bg-amber-500/10 text-amber-800 dark:text-amber-200 border border-amber-500/30 hover:bg-amber-500/20 transition-colors whitespace-nowrap">
          <x-ui.icon name="calendar" class="mr-1.5 size-3.5" /> <span>Service Demo</span>
          </a>
        @endif
        <a href="{{ route('home') }}#pricing" class="hidden md:inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] hover:opacity-90 transition-opacity whitespace-nowrap">
          Claim Handle
        </a>
        <!-- Mobile hamburger -->
        <button type="button"
                @click="mobileNavOpen = !mobileNavOpen"
                class="lg:hidden p-2 rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink)]"
                aria-label="Toggle navigation menu"
                :aria-expanded="mobileNavOpen">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16M4 6h16M4 18h16"/></svg>
        </button>
      </div>
    </div>

    <!-- Mobile Navigation Sheet -->
    <div x-show="mobileNavOpen"
         x-transition.opacity.duration.200ms
         @click.away="mobileNavOpen = false"
         class="platform-mobile-nav lg:hidden border-b px-6 py-5 shadow-lg space-y-3 text-sm font-medium"
         style="display: none;">
      @if ($demoStore || $demoService)
        <div class="p-3 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] space-y-2 mb-2">
          <span class="text-[10px] font-bold text-[var(--ink-3)] uppercase tracking-wider block">Live Storefront Demos</span>
          @if ($demoService)
            <a href="{{ \App\Shared\Support\StoreUrl::homeForSlug($demoService) }}" class="flex items-center justify-between p-2 rounded bg-amber-500/10 text-amber-800 dark:text-amber-200 font-bold text-xs border border-amber-500/30">
              <span class="inline-flex items-center"><x-ui.icon name="calendar" class="mr-2 size-3.5" />Service Booking Demo</span>
              <span>&rarr;</span>
            </a>
          @endif
          @if ($demoStore)
            <a href="{{ \App\Shared\Support\StoreUrl::homeForSlug($demoStore) }}" class="flex items-center justify-between p-2 rounded bg-[var(--brand-wash)] text-[var(--brand-text)] font-bold text-xs border border-[var(--brand-edge)]">
              <span class="inline-flex items-center"><x-ui.icon name="storefront" class="mr-2 size-3.5" />Retail Store Demo</span>
              <span>&rarr;</span>
            </a>
          @endif
        </div>
      @endif
      @if ($showDiscovery)
        <a href="{{ route('discovery.index') }}" @class([
          'block py-1',
          'text-[var(--brand-text)] font-bold' => $active === 'discover',
          'text-[var(--ink)]' => $active !== 'discover',
        ])>Discover Merchants</a>
      @endif
      @if ($showDirectory)
        <a href="{{ route('directory.index') }}" @class([
          'block py-1',
          'text-[var(--brand-text)] font-bold' => $active === 'directory',
          'text-[var(--ink)]' => $active !== 'directory',
        ])>Directory</a>
      @endif
      <a href="{{ route('directory.products') }}" class="block py-1 text-[var(--platform-ink)]">Browse products</a>
      <a href="{{ route('directory.services') }}" class="block py-1 text-[var(--platform-ink)]">Find a service</a>
      <a href="{{ route('home') }}#how-it-works" class="block py-1 text-[var(--platform-ink)]">How it works</a>
      <a href="{{ route('home') }}#pricing" class="block py-1 text-[var(--platform-ink)]">Pricing</a>
      <button type="button" @click="$dispatch('marketplace-open')" class="block w-full py-1 text-left font-bold text-[var(--platform-brand)]">Help me buy</button>
      <a href="{{ route('design-system') }}" class="block py-1 text-[var(--platform-ink-muted)]">Design System</a>
      <div class="pt-3 border-t border-[var(--rule)]">
        <a href="{{ route('home') }}#pricing" class="block text-center py-2.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs">
          Start Your Storefront
        </a>
      </div>
    </div>
</header>
