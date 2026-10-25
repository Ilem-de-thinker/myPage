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
        <div x-data="sharePage(@jsExpr(['url' => url()->current(), 'title' => $page->name]))" class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[10px] text-[var(--ink-3)] block uppercase tracking-wider">Merchant Online</span>
                <span class="text-xs font-bold text-[var(--ink)]">{{ $online ? $page->name.' '.$online : $page->name }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" @click="share()" aria-label="Share this storefront" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius)] border border-[var(--rule)] text-[var(--ink)] hover:bg-[var(--surface)]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 13.5 6.6 3.9M15.3 6.6 8.7 10.5"/></svg>
                </button>
                @if ($page->whatsapp_number)
                    <a href="tel:{{ preg_replace('/\D+/', '', $page->whatsapp_number) }}" aria-label="Call {{ $page->name }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius)] border border-[var(--rule)] text-[var(--ink)] hover:bg-[var(--surface)]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92z"/></svg>
                    </a>
                @endif
                <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="min-h-11 px-3 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold flex items-center gap-2 shadow-sm hover:opacity-95 transition-opacity">
                    <x-ui.whatsapp-mark />
                    <span class="hidden sm:inline">Chat on WhatsApp</span><span class="sm:hidden">Chat</span>
                </a>
            </div>
        </div>
    </aside>

    <aside aria-label="WhatsApp quick concierge" class="hidden md:flex fixed right-0 top-1/2 -translate-y-1/2 z-40">
        <a href="{{ $wa }}"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex items-center gap-2 py-3.5 px-2 rounded-l-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold shadow-md hover:translate-x-[-2px] transition-transform"
           style="writing-mode: vertical-rl;">
            <x-ui.whatsapp-mark :rotate="true" />
            <span>WhatsApp Concierge</span>
        </a>
    </aside>
@endif
