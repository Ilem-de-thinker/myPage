{{--
    The in-page light/dark switch.

    Reads the attribute that is already on <html> and writes the other one. The
    attribute is applied before first paint by x-ui.theme-boot, so by the time
    this button exists the page is already the right colour and never flashes.

    Storage is per-origin, which is what makes the choice stick on a seller's own
    custom domain rather than following a visitor between two shops.

    On a storefront this layers on top of the seller's own surface mode: the
    inline token block renders the seller's setting when no attribute is present,
    and this writes one only once the visitor has actually chosen.
--}}
<button type="button"
        data-theme-toggle
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink-2)] transition-colors hover:bg-[var(--surface)] hover:text-[var(--ink)]"
        aria-label="Switch between light and dark"
        title="Switch to dark">
    {{-- The icon states what clicking will give you, not what you already have. --}}
    <svg data-theme-icon="light" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
    </svg>
    <svg data-theme-icon="dark" class="w-4 h-4" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
    </svg>
</button>
