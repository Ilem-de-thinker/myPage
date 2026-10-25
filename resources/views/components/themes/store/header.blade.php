@props(['page'])

@php
    use App\Shared\Support\StoreUrl;

    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::homePath($page);
    $themePreset = $page->settings['theme_preset'] ?? 'catalog';
@endphp

{{--
    Fixed so the hero photograph runs to the top edge behind it. At rest it is a
    bare wordmark on nothing; once the page scrolls it takes a translucent ground
    and a hairline. That handover is the one orchestrated moment on the page.
--}}
<header data-nav-style="{{ $themePreset }}" class="theme-nav fixed top-0 left-0 right-0 z-40 transition-all duration-300"
        :class="scrolled ? 'h-14 bg-[var(--brand-veil)] backdrop-blur-md shadow-sm border-b border-[var(--rule)]' : 'h-16 bg-transparent border-b border-transparent'">
    <div class="shell h-full flex items-center justify-between gap-8">
        <a href="{{ $home }}" class="flex items-center gap-1.5 text-base font-extrabold tracking-tight text-[var(--ink)] whitespace-nowrap shrink-0">
            {{-- The logo the seller uploaded under Brand, when they have one. With
                 no logo the wordmark is the name itself: never an empty box. --}}
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

        <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-[var(--ink-2)]" aria-label="Store navigation">
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.products*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.products*')]) @if(request()->routeIs('storefront.products*')) aria-current="page" @endif>Products</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.about', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.about*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.about*')]) @if(request()->routeIs('storefront.about*')) aria-current="page" @endif>About</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.contact*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.contact*')]) @if(request()->routeIs('storefront.contact*')) aria-current="page" @endif>Contact</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.track-order', $storefront) }}" @class(['whitespace-nowrap transition-colors', 'font-bold text-[var(--ink)]' => request()->routeIs('storefront.track-order*'), 'hover:text-[var(--ink)]' => ! request()->routeIs('storefront.track-order*')]) @if(request()->routeIs('storefront.track-order*')) aria-current="page" @endif>Track Order</a>
        </nav>

        <div class="flex items-center gap-3 shrink-0">
            {{-- The seller's surface mode is the default; this is the visitor's override. --}}
            <x-ui.theme-toggle />

            @if (($page->settings['ai_enabled'] ?? true) && config('ai.enabled'))
                <button type="button" @click="$dispatch('ai.open')" class="hidden min-h-11 items-center gap-1.5 rounded-[var(--radius)] border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-3.5 text-xs font-bold text-[var(--brand-text)] lg:inline-flex" aria-label="Ask the AI assistant"><x-ui.icon name="spark" size="size-4" /> Ask AI</button>
            @endif

            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.cart', $storefront) }}"
               class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius)] bg-[var(--brand-wash)] px-3.5 py-1.5 text-xs font-semibold text-[var(--brand-text)] border border-[var(--brand-edge)] transition-colors hover:bg-[var(--brand-wash-2)]"
               aria-label="View shopping bag">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="hidden sm:inline">Bag</span>
                <span class="px-1.5 py-0.2 rounded-full bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-[11px] tabular-nums" x-text="$store.cart.count" aria-live="polite">0</span>
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
         role="navigation" aria-label="Mobile store navigation"
         style="display: none;">
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">All Products</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.about', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">Our Story</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">Contact Studio</a>
        <a @click="mobileNavOpen = false" href="{{ \App\Shared\Support\StoreUrl::path('storefront.track-order', $storefront) }}" class="flex min-h-11 items-center text-[var(--ink)]">Track Waybill Reference</a>
        <div class="pt-3 border-t border-[var(--rule)] flex items-center justify-between">
            @if (($page->settings['ai_enabled'] ?? true) && config('ai.enabled'))
                <button type="button" @click="$dispatch('ai.open'); mobileNavOpen = false" class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] px-3 text-xs font-bold text-[var(--brand-text)]"><x-ui.icon name="spark" size="size-4" /> Ask AI</button>
            @endif
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.cart', $storefront) }}" class="flex items-center gap-2 text-xs font-bold text-[var(--brand-text)]">
                <span>Shopping Bag</span>
                <span class="px-2 py-0.5 rounded-full bg-[var(--brand)] text-[var(--brand-ink)] tabular-nums" x-text="$store.cart.count">0</span>
            </a>
            @if ($wa = \App\Shared\Support\WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your storefront.'))
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-[var(--brand-text)]">
                    WhatsApp Us
                </a>
            @endif
        </div>
    </div>
</header>
