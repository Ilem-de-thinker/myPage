@props(['page'])

<div x-data="storeAssistant(@jsExpr(['endpoint' => \App\Shared\Support\StoreUrl::path('storefront.ai.assistant', ['slug' => $page->slug]), 'csrf' => csrf_token()]))" @ai-open.window="open = true" @keydown.escape.window="open = false" class="fixed inset-x-4 bottom-[calc(11rem+env(safe-area-inset-bottom))] z-40 sm:inset-x-auto sm:right-6 sm:bottom-24 sm:w-96">
    <div x-show="open" x-cloak class="mb-3 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--rule)] bg-[var(--card)] shadow-[var(--shadow-lift)]">
        <div class="border-b border-[var(--rule)] bg-[var(--brand-wash)] px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-sm font-bold text-[var(--ink)]">Ask {{ $page->name }}</p><p class="mt-1 text-[11px] text-[var(--ink-2)]">Mercho assistant · Uses published store information</p></div>
                <button type="button" @click="open = false" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius)] text-[var(--ink-2)] hover:bg-[var(--brand-wash-2)]" aria-label="Close assistant">&times;</button>
            </div>
        </div>
        <div class="max-h-72 space-y-3 overflow-y-auto p-4" aria-live="polite">
            <p x-show="!answer" class="text-xs leading-5 text-[var(--ink-2)]">Ask about products, services, prices, or how to contact the merchant.</p>
            <p x-show="answer" x-text="answer" class="whitespace-pre-line text-sm leading-6 text-[var(--ink)]"></p>
            <p x-show="loading" class="text-xs text-[var(--ink-3)]">Checking the merchant’s information…</p>
        </div>
        <form @submit.prevent="ask" class="flex gap-2 border-t border-[var(--rule)] p-3">
            <label class="sr-only" for="store-assistant-message">Ask a question</label>
            <input id="store-assistant-message" x-model="message" type="text" maxlength="2000" placeholder="Ask a question…" class="min-h-11 min-w-0 flex-1 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] px-3 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]">
            <button type="submit" :disabled="loading" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius)] bg-[var(--brand)] px-3 text-sm font-bold text-[var(--brand-ink)] disabled:opacity-50" aria-label="Send question">&rarr;</button>
        </form>
    </div>
    <button type="button" @click="open = !open" class="ml-auto inline-flex min-h-12 items-center gap-2 rounded-full bg-[var(--brand)] px-5 text-sm font-bold text-[var(--brand-ink)] shadow-[var(--shadow-lift)] transition-transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-[var(--brand)] focus:ring-offset-2" :aria-expanded="open"><x-ui.icon name="spark" size="size-4" />Ask Mercho about this {{ $page->theme->value === 'service' ? 'service' : 'store' }}</button>
</div>
