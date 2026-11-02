@props(['page'])

<div
    x-data="storeSearch(@jsExpr(['endpoint' => \App\Shared\Support\StoreUrl::path('storefront.ai.search', ['slug' => $page->slug]), 'csrf' => csrf_token()]))"
    @store-search-open.window="openPanel()"
    @keydown.escape.window="open && closePanel()"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="store-search-title">
        <div class="absolute inset-0 bg-[var(--ink)]/45 backdrop-blur-[2px]" @click="closePanel()"></div>

        <section
            x-show="open"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="-translate-y-4 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="-translate-y-4 opacity-0"
            class="absolute inset-x-0 top-0 flex max-h-[92dvh] flex-col rounded-b-[2rem] bg-[var(--card)] shadow-[var(--shadow-lift)] sm:inset-x-4 sm:mx-auto sm:mt-6 sm:max-w-2xl sm:rounded-[var(--radius-lg)]"
            aria-label="Search this store"
        >
            <header class="flex items-start justify-between gap-4 border-b border-[var(--rule)] px-5 pb-4 pt-5 sm:px-7">
                <div class="min-w-0">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--brand)]">In-store search</p>
                    <h2 id="store-search-title" class="mt-1 font-display text-xl font-extrabold tracking-tight text-[var(--ink)]">Search {{ $page->name }}</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--ink-2)]">Describe what you want — by product, category or price. Results come from this store only.</p>
                </div>
                <button type="button" @click="closePanel()" x-ref="closeButton" class="rounded-full p-2 text-[var(--ink-2)] transition hover:bg-[var(--brand-wash)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]" aria-label="Close search">
                    <x-ui.icon name="close" class="size-5" />
                </button>
            </header>

            <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-7">
                <form @submit.prevent="submit" class="flex gap-2">
                    <label for="store-search-message" class="sr-only">Search this store</label>
                    <input
                        id="store-search-message"
                        x-ref="input"
                        x-model="message"
                        type="search"
                        maxlength="400"
                        placeholder="e.g. ankara under ₦15,000"
                        class="min-h-11 min-w-0 flex-1 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] px-3 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]"
                        @keydown.enter.exact.prevent="submit()"
                    />
                    <button type="submit" :disabled="loading || !message.trim()" class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius)] bg-[var(--brand)] px-4 text-sm font-bold text-[var(--brand-ink)] transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                        <x-ui.icon name="search" class="size-4" />
                        <span x-text="loading ? 'Searching…' : 'Search'"></span>
                    </button>
                </form>

                <p x-show="mode === 'keyword' && searched" class="mt-3 text-xs leading-5 text-[var(--ink-2)]" role="status">Quick text search — Mercho is temporarily unavailable, so results match your words literally.</p>

                <div x-show="searched && appliedChips.length" class="mt-3 flex flex-wrap gap-2">
                    <template x-for="chip in appliedChips" :key="chip">
                        <span class="rounded-full border border-[var(--brand-edge)] bg-[var(--brand-wash)] px-3 py-1.5 text-xs font-bold text-[var(--brand-text)]" x-text="chip"></span>
                    </template>
                </div>

                <p x-show="error" x-text="error" class="mt-4 rounded-[var(--radius)] border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert"></p>

                <div x-show="loading" class="mt-6 grid gap-3" aria-live="polite" aria-label="Searching">
                    <div class="marketplace-loading-line"><span></span><span></span><span></span></div>
                    <div class="marketplace-skeleton"></div>
                    <div class="marketplace-skeleton"></div>
                </div>

                <div x-show="questions.length && !loading" class="mt-5 rounded-[var(--radius)] bg-[var(--brand-wash)] p-4">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[var(--ink-2)]">One detail could help</p>
                    <template x-for="question in questions" :key="question">
                        <button type="button" @click="chooseQuestion(question)" class="mt-2 block text-left text-sm font-semibold text-[var(--brand-text)] hover:underline" x-text="question"></button>
                    </template>
                </div>

                <div x-show="results.length && !loading" class="mt-5 space-y-3">
                    <h3 class="font-display text-base font-bold text-[var(--ink)]" x-text="results.length + (results.length === 1 ? ' match' : ' matches')"></h3>
                    <template x-for="(result, index) in results" :key="result.type + result.url">
                        <article class="rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] p-4 transition hover:border-[var(--brand-edge)]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="font-display text-base font-bold text-[var(--ink)]" x-text="result.name"></h4>
                                        <span class="rounded-full bg-[var(--brand-wash)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--brand-text)]" x-text="result.type === 'service' ? 'Service' : 'Product'"></span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-[var(--ink-2)]" x-text="result.description"></p>
                                </div>
                                <span class="shrink-0 text-right font-display text-sm font-extrabold text-[var(--ink)]">
                                    <span x-text="result.price"></span>
                                    <del x-show="result.original_price" class="ml-1 block text-[10px] font-semibold text-[var(--ink-3)] tabular-nums" x-text="result.original_price"></del>
                                </span>
                            </div>
                            <a :href="result.url" class="mt-3 inline-flex min-h-10 items-center rounded-[var(--radius)] bg-[var(--brand)] px-3 text-xs font-bold text-[var(--brand-ink)]">View on store</a>
                        </article>
                    </template>
                </div>

                <p x-show="searched && !loading && !results.length && !error" class="mt-6 rounded-[var(--radius)] border border-dashed border-[var(--rule)] p-5 text-sm leading-6 text-[var(--ink-2)]">
                    Nothing in this store matches yet. Try different words, a category, or a wider budget.
                </p>

                <p x-show="!searched && !loading && !error" class="mt-6 text-xs leading-5 text-[var(--ink-2)]">
                    Mercho helps read your query. Product details and prices come from this store’s published page.
                </p>
            </div>
        </section>
    </div>
</div>
