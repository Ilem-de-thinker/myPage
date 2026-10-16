@props(['page'])

@php
    use App\Shared\Support\WhatsApp;

    $wa = WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your storefront.');
    $online = $page->city ?: $page->state;
@endphp

{{--
    WhatsApp is the conversion channel, so it is on every page. On a phone it is
    a bar along the bottom rather than a floating circle: a circle covers the
    product you are trying to tap, and its label is the only part anyone reads.
--}}
@if ($wa)
    <aside aria-label="WhatsApp quick order" class="fixed bottom-0 left-0 right-0 z-40 bg-[var(--card)] border-t border-[var(--rule)] p-3 md:hidden shadow-lg" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
        <div class="flex items-center justify-between gap-3">
            <div>
                <span class="text-[10px] text-[var(--ink-3)] block uppercase tracking-wider">Merchant Online</span>
                <span class="text-xs font-bold text-[var(--ink)]">{{ $online ? $page->name.' '.$online : $page->name }}</span>
            </div>
            <a href="{{ $wa }}"
               target="_blank"
               rel="noopener noreferrer"
               class="h-10 px-4 rounded-[var(--radius)] bg-[#25D366] text-white text-xs font-bold flex items-center gap-2 shadow-sm hover:opacity-95 transition-opacity">
                <x-ui.whatsapp-mark />
                <span>Chat on WhatsApp</span>
            </a>
        </div>
    </aside>

    <aside aria-label="WhatsApp quick concierge" class="hidden md:flex fixed right-0 top-1/2 -translate-y-1/2 z-40">
        <a href="{{ $wa }}"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex items-center gap-2 py-3.5 px-2 rounded-l-[var(--radius)] bg-[#25D366] text-white text-xs font-bold shadow-md hover:translate-x-[-2px] transition-transform"
           style="writing-mode: vertical-rl;">
            <x-ui.whatsapp-mark :rotate="true" />
            <span>WhatsApp Concierge</span>
        </a>
    </aside>
@endif