@props(['page'])

@php
    use App\Shared\Support\StoreUrl;

    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::home($page);
@endphp

{{--
    Fixed so the hero photograph runs to the top edge behind it. At rest it is a
    bare wordmark on nothing; once the page scrolls it takes a translucent ground
    and a hairline. That handover is the one orchestrated moment on the page.
--}}
<header class="fixed top-0 left-0 right-0 z-40 transition-all duration-300"
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

        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-[var(--ink-2)]" aria-label="Store navigation">
            <a href="{{ route('storefront.products', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Products</a>
            <a href="{{ route('storefront.about', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">About</a>
            <a href="{{ route('storefront.contact', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Contact</a>
            <a href="{{ route('storefront.track-order', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Track Order</a>
        </nav>

        <div class="flex items-center gap-3 shrink-0">
            {{-- The seller's surface mode is the default; this is the visitor's override. --}}
            <x-ui.theme-toggle />

            <a href="{{ route('storefront.cart', $storefront) }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] hover:bg-[var(--brand-wash-2)] text-xs font-semibold transition-colors"
               aria-label="View shopping bag">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="hidden sm:inline">Bag</span>
                <span class="px-1.5 py-0.2 rounded-full bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-[11px] tabular-nums" x-text="$store.cart.count" aria-live="polite">0</span>
            </a>

            <button type="button"
                    @click="mobileNavOpen = !mobileNavOpen"
                    class="md:hidden p-2 rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink)] bg-[var(--paper)]"
                    aria-label="Toggle navigation menu"
                    :aria-expanded="mobileNavOpen">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16M4 6h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile Nav Sheet --}}
    <div x-show="mobileNavOpen"
         x-transition.opacity.duration.200ms
         @click.away="mobileNavOpen = false"
         class="md:hidden border-b border-[var(--rule)] bg-[var(--paper)] px-6 py-5 shadow-lg space-y-3 text-sm font-medium"
         style="display: none;">
        <a href="{{ route('storefront.products', $storefront) }}" class="block py-1 text-[var(--ink)]">All Products</a>
        <a href="{{ route('storefront.about', $storefront) }}" class="block py-1 text-[var(--ink)]">Our Story</a>
        <a href="{{ route('storefront.contact', $storefront) }}" class="block py-1 text-[var(--ink)]">Contact Studio</a>
        <a href="{{ route('storefront.track-order', $storefront) }}" class="block py-1 text-[var(--ink)]">Track Waybill Reference</a>
        <div class="pt-3 border-t border-[var(--rule)] flex items-center justify-between">
            <a href="{{ route('storefront.cart', $storefront) }}" class="flex items-center gap-2 text-xs font-bold text-[var(--brand-text)]">
                <span>Shopping Bag</span>
                <span class="px-2 py-0.5 rounded-full bg-[var(--brand)] text-[var(--brand-ink)] tabular-nums" x-text="$store.cart.count">0</span>
            </a>
            @if ($wa = \App\Shared\Support\WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your storefront.'))
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-[#25D366]">
                    WhatsApp Us
                </a>
            @endif
        </div>
    </div>
</header>