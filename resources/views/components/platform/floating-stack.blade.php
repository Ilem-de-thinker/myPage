<div
    x-data="{ open: false }"
    x-on:marketplace-open.window="open = true"
    x-on:marketplace-closed.window="open = false"
    class="platform-floating-stack fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] right-4 z-40 flex flex-col items-end gap-3 sm:right-6 md:bottom-6"
    aria-label="Quick actions"
>
    <button
        type="button"
        @click="$dispatch('marketplace-open')"
        class="inline-flex min-h-12 items-center gap-2 rounded-full bg-[var(--platform-accent)] px-4 text-sm font-extrabold text-white shadow-[var(--platform-shadow-lg)] transition hover:-translate-y-0.5 hover:bg-[var(--platform-accent-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--platform-accent)] focus:ring-offset-2"
        aria-label="Open Help me buy"
    >
        <x-ui.icon name="spark" class="size-4" aria-hidden="true" />
        <span>Help me buy</span>
    </button>

    <button
        type="button"
        @click="$dispatch('support-open')"
        class="inline-flex min-h-12 items-center gap-2 rounded-full bg-[var(--platform-ink)] px-4 text-sm font-extrabold text-[var(--platform-ink-invert)] shadow-[var(--platform-shadow-lg)] transition hover:-translate-y-0.5 hover:bg-[var(--platform-ink-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--platform-ink)] focus:ring-offset-2"
        aria-label="Open support"
    >
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.7 9.7 0 0 1-4.2-1L3 21l1.5-4.1A8.4 8.4 0 0 1 3 11.5 8.5 8.5 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5Z" /><path d="M8 12h.01M12 12h.01M16 12h.01" stroke-linecap="round" /></svg>
        <span>Support</span>
    </button>
</div>
