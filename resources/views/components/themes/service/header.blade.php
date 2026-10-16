@props(['page'])

@php
    use App\Shared\Support\StoreUrl;
    use App\Shared\Support\WhatsApp;

    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::home($page);
    $wa = WhatsApp::link($page, 'Hello '.$page->name.', I would like to inquire about booking an appointment.');
@endphp

{{-- Fixed so the hero photograph runs to the top edge behind it. --}}
<header class="fixed top-0 left-0 right-0 z-40 transition-all duration-300"
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

        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-[var(--ink-2)]" aria-label="Service navigation">
            <a href="{{ route('storefront.services', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Services &amp; Menu</a>
            <a href="{{ route('storefront.book', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Book Appointment</a>
            <a href="{{ route('storefront.about', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">About Studio</a>
            <a href="{{ route('storefront.contact', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Location &amp; Hours</a>
            <a href="{{ route('storefront.manage-booking', $storefront) }}" class="hover:text-[var(--ink)] transition-colors whitespace-nowrap">Manage Booking</a>
        </nav>

        <div class="flex items-center gap-3 shrink-0">
            {{-- The seller's surface mode is the default; this is the visitor's override. --}}
            <x-ui.theme-toggle />

            <a href="{{ route('storefront.book', $storefront) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold shadow-sm hover:opacity-95 transition-opacity">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                <span>Reserve Slot</span>
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
        <a href="{{ route('storefront.services', $storefront) }}" class="block py-1 text-[var(--ink)]">Services &amp; Packages</a>
        <a href="{{ route('storefront.book', $storefront) }}" class="block py-1 text-[var(--ink)] font-bold text-[var(--brand-text)]">Book Appointment Slot</a>
        <a href="{{ route('storefront.about', $storefront) }}" class="block py-1 text-[var(--ink)]">The Studio &amp; Team</a>
        <a href="{{ route('storefront.contact', $storefront) }}" class="block py-1 text-[var(--ink)]">Studio Directions</a>
        <a href="{{ route('storefront.manage-booking', $storefront) }}" class="block py-1 text-[var(--ink)]">Manage Booking Reference</a>
        <div class="pt-3 border-t border-[var(--rule)] flex items-center justify-between">
            @if ($wa)
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-[#25D366]">
                    Chat with Studio WhatsApp
                </a>
            @endif
        </div>
    </div>
</header>