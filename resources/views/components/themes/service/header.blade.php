@props(['page'])

@php
    use App\Shared\Support\StoreUrl;
    use App\Shared\Support\WhatsApp;

    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::homePath($page);
    $wa = WhatsApp::link($page, 'Hello '.$page->name.', I would like to inquire about booking an appointment.');
    $themePreset = $page->settings['theme_preset'] ?? 'minimal-swiss';
@endphp

{{-- Fixed so the hero photograph runs to the top edge behind it. --}}
<header data-nav-style="{{ $themePreset }}" class="theme-nav fixed top-0 left-0 right-0 z-40 transition-all duration-300"
        :class="scrolled ? 'h-14 bg-[var(--brand-veil)] backdrop-blur-md shadow-sm border-b border-[var(--rule)]' : 'h-16 bg-transparent border-b border-transparent'">
    <div class="shell h-full flex items-center justify-between gap-8">
        <a href="{{ $home }}" class="flex items-center gap-1.5 text-base font-extrabold tracking-tight text-[var(--ink)] whitespace-nowrap shrink-0">
            @if ($page->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}"
                     alt="{{ $page->name }}"
                     width="28"
                     height="28"
                     class="w-7 h-7 rounded-[var(--radius-sm)] object-cover">
            @else
                <span class="w-2.5 h-2.5 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
            @endif
            <span style="font-family: var(--font-heading)">{{ $page->name }}</span>
            {{-- The one piece of trust we can offer a stranger. --}}
            <x-themes.shared.verified-badge :page="$page" />
        </a>

        <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-[var(--ink-2)]" aria-label="Service navigation">
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.services', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.services*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.services*')]) @if(request()->routeIs('storefront.services*')) aria-current="page" @endif>Services &amp; Menu</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.book*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.book*')]) @if(request()->routeIs('storefront.book*')) aria-current="page" @endif>Book Appointment</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.about', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.about*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.about*')]) @if(request()->routeIs('storefront.about*')) aria-current="page" @endif>About Studio</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.contact*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.contact*')]) @if(request()->routeIs('storefront.contact*')) aria-current="page" @endif>Location &amp; Hours</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.manage-booking', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.manage-booking*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.manage-booking*')]) @if(request()->routeIs('storefront.manage-booking*')) aria-current="page" @endif>Manage Booking</a>
        </nav>

        <div class="flex items-center gap-3 shrink-0">
            {{-- The seller's surface mode is the default; this is the visitor's override. --}}
            <x-ui.theme-toggle />

            @if (($page->settings['ai_enabled'] ?? true) && app(\App\Domains\Ai\Services\AiSettings::class)->enabled())
                <button type="button" @click="$dispatch('store-search-open')" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] text-[var(--ink)] transition-colors hover:bg-[var(--brand-wash)]" aria-label="Search this store"><x-ui.icon name="search" size="size-4" /></button>
                <button type="button" @click="$dispatch('ai-open')" class="hidden min-h-11 items-center gap-1.5 rounded-[var(--radius)] border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-3.5 text-xs font-bold text-[var(--brand-text)] lg:inline-flex" aria-label="Ask the Mercho assistant"><x-ui.icon name="spark" size="size-4" /> Ask Mercho</button>
            @endif

            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', $storefront) }}"
               class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius)] bg-[var(--brand)] px-3.5 py-2 text-xs font-bold text-[var(--brand-ink)] shadow-sm transition-opacity hover:opacity-95">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                <span class="hidden sm:inline">Reserve Slot</span>
            </a>

            <button type="button"
                    @click="mobileNavOpen = !mobileNavOpen"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--paper)] p-2 text-[var(--ink)] shadow-sm lg:hidden"
                    aria-label="Toggle navigation menu"
                    :aria-expanded="mobileNavOpen">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16M4 6h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile Nav Sheet --}}
    <div x-cloak x-show="mobileNavOpen"
         x-transition.opacity.duration.200ms
         @click.away="mobileNavOpen = false"
         class="theme-nav-drawer absolute left-0 right-0 top-full max-h-[calc(100dvh-4rem)] overflow-y-auto border-b border-[var(--rule)] bg-[var(--paper)] px-5 py-4 shadow-xl lg:hidden space-y-1 text-sm font-medium"
         role="navigation" aria-label="Mobile service navigation"
         style="display: none;">
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.services', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">Services &amp; Packages</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)] font-bold text-[var(--brand-text)]">Book Appointment Slot</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.about', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">The Studio &amp; Team</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">Studio Directions</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.manage-booking', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">Manage Booking Reference</a>
        <div class="pt-3 border-t border-[var(--rule)] flex items-center justify-between">
            @if (($page->settings['ai_enabled'] ?? true) && app(\App\Domains\Ai\Services\AiSettings::class)->enabled())
                <button type="button" @click="$dispatch('store-search-open'); mobileNavOpen = false" class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] px-3 text-xs font-bold text-[var(--brand-text)]"><x-ui.icon name="search" size="size-4" /> Search store</button>
                <button type="button" @click="$dispatch('ai-open'); mobileNavOpen = false" class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] px-3 text-xs font-bold text-[var(--brand-text)]"><x-ui.icon name="spark" size="size-4" /> Ask Mercho</button>
            @endif
            @if ($wa)
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-[var(--brand-text)]">
                    Chat with Studio WhatsApp
                </a>
            @endif
        </div>
    </div>
</header>
