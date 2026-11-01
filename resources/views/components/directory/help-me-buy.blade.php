<div
    x-data="marketplaceAssistant"
    x-on:marketplace-open.window="openPanel()"
    x-on:keydown.escape.window="open && closePanel()"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="assistant-title" style="display: none">
        <div class="absolute inset-0 bg-[var(--platform-ink)]/45 backdrop-blur-[2px]" @click="closePanel()"></div>

        <section
            x-show="open"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-full opacity-0 sm:translate-x-full sm:translate-y-0"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave-end="translate-y-full opacity-0 sm:translate-x-full sm:translate-y-0"
            class="marketplace-panel absolute inset-x-0 bottom-0 flex max-h-[92dvh] min-h-[72dvh] flex-col rounded-t-[2rem] bg-[var(--platform-paper)] shadow-2xl sm:inset-y-0 sm:left-auto sm:right-0 sm:max-h-none sm:min-h-0 sm:w-[min(34rem,100vw)] sm:rounded-none sm:border-l sm:border-[var(--platform-line)]"
            aria-label="Help me buy"
        >
            <header class="flex items-start justify-between gap-4 border-b border-[var(--platform-line)] px-5 pb-4 pt-5 sm:px-7">
                <div class="min-w-0">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--platform-accent)]">Guided discovery</p>
                    <h2 id="assistant-title" class="mt-1 font-display text-2xl font-extrabold tracking-tight text-[var(--platform-ink)]">Help me buy</h2>
                    <p class="mt-1 text-sm leading-6 text-[var(--platform-ink-muted)]">Tell us what you need. We search published listings and hand you to the merchant.</p>
                </div>
                <button type="button" @click="closePanel()" x-ref="closeButton" class="rounded-full p-2 text-[var(--platform-ink-muted)] transition hover:bg-[var(--platform-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--platform-accent)]" aria-label="Close Help me buy">
                    <x-ui.icon name="close" class="size-5" />
                </button>
            </header>

            <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-7" x-ref="panelBody">
                <div class="mb-5 flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <template x-for="suggestion in suggestions" :key="suggestion">
                        <button type="button" @click="useSuggestion(suggestion)" class="shrink-0 rounded-full border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 py-2 text-xs font-semibold text-[var(--platform-ink-muted)] transition hover:border-[var(--platform-accent)] hover:text-[var(--platform-accent)]" x-text="suggestion"></button>
                    </template>
                </div>

                <form @submit.prevent="submit" class="space-y-3">
                    <label for="marketplace-message" class="sr-only">What are you looking for?</label>
                    <textarea
                        id="marketplace-message"
                        x-ref="input"
                        x-model="message"
                        rows="4"
                        maxlength="1200"
                        class="w-full resize-none rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-card)] p-4 text-sm leading-6 text-[var(--platform-ink)] outline-none transition focus:border-[var(--platform-accent)] focus:ring-2 focus:ring-[var(--platform-accent)]/15"
                        placeholder="What are you looking for? (e.g., ankara, shoes, haircut)"
                        @keydown.enter.exact.prevent="submit()"
                    ></textarea>
                    <button type="submit" :disabled="loading || !message.trim()" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--platform-accent)] px-5 text-sm font-extrabold text-[var(--platform-ink-invert)] transition hover:bg-[var(--platform-accent-hover)] disabled:cursor-not-allowed disabled:opacity-50">
                        <x-ui.icon name="spark" class="size-4" />
                        <span x-text="submitLabel"></span>
                    </button>
                </form>

                <p class="mt-3 text-xs leading-5 text-[var(--platform-ink-muted)]">Mercho-assisted search. Names, prices and locations come from the merchant’s published page. No checkout happens here.</p>

                <p x-show="limitReached" class="mt-4 rounded-xl border border-[var(--platform-accent-border)] bg-[var(--platform-accent-wash)] p-4 text-sm leading-6 text-[var(--platform-ink)]" role="status">
                    You have reached today’s free guided-search limit. You can keep browsing the directory normally.
                    <a href="{{ route('directory.stores') }}" class="mt-3 inline-flex min-h-10 items-center rounded-lg bg-[var(--platform-ink)] px-3 text-xs font-bold text-[var(--platform-ink-invert)]">Browse stores</a>
                </p>

                <p x-show="error && !limitReached" x-text="error" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert"></p>

                <div x-show="loading" class="mt-7 grid gap-3" aria-live="polite" aria-label="Searching">
                    <div class="marketplace-loading-line"><span></span><span></span><span></span></div>
                    <div class="marketplace-skeleton"></div>
                    <div class="marketplace-skeleton"></div>
                </div>

                <div x-show="questions.length" class="mt-5 rounded-2xl bg-[var(--platform-surface)] p-4">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[var(--platform-ink-muted)]">One detail could help</p>
                    <template x-for="question in questions" :key="question">
                        <button type="button" @click="useQuestion(question)" class="mt-2 block text-left text-sm font-semibold text-[var(--platform-support)] hover:underline" x-text="question"></button>
                    </template>
                </div>

                <div x-show="results.length" class="mt-7 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg font-bold text-[var(--platform-ink)]">Published matches</h3>
                            <span class="text-xs text-[var(--platform-ink-muted)]" x-text="resultCountLabel"></span>
                        </div>
                        <select x-model="sort" @change="sortResults()" class="min-h-10 rounded-lg border border-[var(--platform-line)] bg-[var(--platform-card)] px-2 text-xs font-semibold text-[var(--platform-ink)] focus:outline-none focus:ring-2 focus:ring-[var(--platform-accent)]">
                            <option value="match">Best match</option>
                            <option value="cheap">Cheapest first</option>
                            <option value="expensive">Highest price</option>
                        </select>
                    </div>

                    <div x-show="appliedChips.length" class="flex flex-wrap gap-2">
                        <template x-for="chip in appliedChips" :key="chip">
                            <span class="rounded-full border border-[var(--platform-accent)]/40 bg-[var(--platform-accent-wash)] px-3 py-1.5 text-[11px] font-bold text-[var(--platform-ink)]" x-text="chip"></span>
                        </template>
                    </div>

                    <template x-for="(result, index) in results" :key="result.id">
                        <article class="marketplace-result-card rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-card)] p-4" :style="'--result-index: ' + index">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-[var(--platform-ink-muted)]" x-text="result.store"></p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <h4 class="font-display text-base font-bold text-[var(--platform-ink)]" x-text="result.name"></h4>
                                        <span x-show="result.verified" class="rounded-full bg-[var(--platform-support-wash)] px-2 py-0.5 text-[10px] font-bold text-[var(--platform-support)]">Verified</span>
                                        <span class="rounded-full bg-[var(--platform-surface)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--platform-ink-muted)]" x-text="typeLabel(result)"></span>
                                    </div>
                                </div>
                                <span class="shrink-0 text-right font-display text-sm font-extrabold text-[var(--platform-ink)]">
                                    <span x-text="result.price"></span>
                                    <del x-show="result.originalPrice" class="ml-1 block text-[10px] font-semibold text-[var(--platform-ink-muted)] tabular-nums" x-text="result.originalPrice"></del>
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-[var(--platform-ink-muted)]" x-text="result.location"></p>
                            <p class="mt-3 text-sm leading-6 text-[var(--platform-ink-muted)]" x-text="result.reason"></p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="result.url" class="inline-flex min-h-10 items-center rounded-lg bg-[var(--platform-ink)] px-3 text-xs font-bold text-[var(--platform-ink-invert)]">View on store</a>
                                <a :href="result.whatsappUrl" class="inline-flex min-h-10 items-center rounded-lg border border-[var(--platform-line-strong)] px-3 text-xs font-bold text-[var(--platform-support)]">WhatsApp</a>
                                <button type="button" @click="toggleCompare(result)" class="inline-flex min-h-10 items-center rounded-lg border border-[var(--platform-line)] px-3 text-xs font-bold text-[var(--platform-ink-muted)]" x-text="compareLabel(result)"></button>
                            </div>
                            <span x-show="index === 0 && sort === 'cheap'" class="mt-3 block text-[11px] font-bold text-[var(--platform-accent)]">Lowest published price in these results</span>
                        </article>
                    </template>
                </div>

                <section x-show="compare.length" class="mt-6 rounded-2xl border border-[var(--platform-support-border)] bg-[var(--platform-support-wash)] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-display text-base font-bold text-[var(--platform-ink)]">Quick comparison</h3>
                            <p class="text-[11px] text-[var(--platform-ink-muted)]">Up to 3 published listings. Prices are merchant-set.</p>
                        </div>
                        <button type="button" @click="compare = []" class="text-xs font-semibold text-[var(--platform-ink-muted)] hover:underline">Clear</button>
                    </div>
                    <div class="mt-3 grid gap-2" :class="compare.length > 1 ? 'sm:grid-cols-2' : ''">
                        <template x-for="item in compare" :key="item.id">
                            <div class="rounded-xl bg-[var(--platform-card)] p-3">
                                <p class="truncate text-xs text-[var(--platform-ink-muted)]" x-text="item.store"></p>
                                <p class="mt-1 line-clamp-2 text-sm font-bold text-[var(--platform-ink)]" x-text="item.name"></p>
                                <p class="mt-2 text-xs font-bold text-[var(--platform-ink)]">
                                    <span x-text="item.price"></span>
                                    <del x-show="item.originalPrice" class="ml-1 text-[10px] font-semibold text-[var(--platform-ink-muted)] tabular-nums" x-text="item.originalPrice"></del>
                                </p>
                                <p class="mt-1 text-xs text-[var(--platform-ink-muted)]" x-text="item.location"></p>
                                <a :href="item.url" class="mt-3 inline-flex min-h-10 items-center text-xs font-bold text-[var(--platform-support)] hover:underline">Open listing</a>
                            </div>
                        </template>
                    </div>
                    <p x-show="compareLimit" class="mt-2 text-[11px] font-semibold text-[var(--platform-accent)]">Compare holds three listings. Remove one to add another.</p>
                </section>

                <p x-show="searched && !loading && !results.length && !error && !limitReached" class="mt-8 rounded-2xl border border-dashed border-[var(--platform-line-strong)] p-5 text-sm leading-6 text-[var(--platform-ink-muted)]">
                    No published match yet. Try a different category, city or budget. You can also browse the full directory.
                </p>

                <p x-show="!searched && !loading" class="mt-8 rounded-2xl bg-[var(--platform-surface)] p-4 text-xs leading-6 text-[var(--platform-ink-muted)]">
                    Help me buy searches products, services and merchant locations. It does not invent stock, prices or availability.
                </p>
            </div>

            <footer class="border-t border-[var(--platform-line)] px-5 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] text-[11px] text-[var(--platform-ink-muted)] sm:px-7 sm:pb-3">
                <span class="flex items-center gap-1.5">
                    <svg class="size-3.5 text-[var(--platform-support)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    Direct handoff. The merchant answers, sells and fulfils.
                </span>
            </footer>
        </section>
    </div>
</div>

@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('marketplaceAssistant', () => ({
            open: false,
            loading: false,
            searched: false,
            message: '',
            suggestions: ['Ankara under ₦15,000', 'A tailor in Lagos', 'Under ₦20,000'],
            results: [],
            originalResults: [],
            applied: {},
            questions: [],
            error: '',
            limitReached: false,
            compareLimit: false,
            sort: 'match',
            compare: [],
            conversation: [],
            controller: null,

            get submitLabel() {
                if (this.loading) {
                    return 'Searching published listings…';
                }

                return this.conversation.length ? 'Refine my options' : 'Find my options';
            },

            get resultCountLabel() {
                return this.results.length + (this.results.length === 1 ? ' result' : ' results') + ' from merchant listings';
            },

            get appliedChips() {
                const chips = [];

                if (this.applied.q) {
                    chips.push('“' + this.applied.q + '”');
                }
                if (this.applied.location) {
                    chips.push('In ' + this.applied.location);
                }
                if (this.applied.budget_min && this.applied.budget_max) {
                    chips.push('₦' + this.applied.budget_min + ' – ₦' + this.applied.budget_max);
                } else if (this.applied.budget_max) {
                    chips.push('Under ₦' + this.applied.budget_max);
                } else if (this.applied.budget_min) {
                    chips.push('From ₦' + this.applied.budget_min);
                }
                if (this.applied.kind) {
                    chips.push(this.applied.kind === 'service' ? 'Services only' : 'Products only');
                }

                return chips;
            },

            typeLabel(result) {
                return result.type === 'service' ? 'Service' : 'Product';
            },

            compareLabel(result) {
                return this.isCompared(result) ? 'Remove compare' : 'Compare';
            },

            openPanel() {
                this.open = true;
                document.body.classList.add('sheet-open');
                this.$nextTick(() => this.$refs.closeButton?.focus());
            },

            useSuggestion(suggestion) {
                this.message = suggestion;
                this.$nextTick(() => this.$refs.input?.focus());
            },

            useQuestion(question) {
                this.message = question + ' ';
                this.$nextTick(() => this.$refs.input?.focus());
            },

            closePanel() {
                this.open = false;
                this.controller?.abort();
                this.loading = false;
                this.limitReached = false;
                this.compareLimit = false;
                document.body.classList.remove('sheet-open');
                window.dispatchEvent(new CustomEvent('marketplace-closed'));
            },

            sortResults() {
                if (this.sort === 'cheap') {
                    this.results = [...this.originalResults].sort((a, b) => Number(a.priceValue) - Number(b.priceValue));
                } else if (this.sort === 'expensive') {
                    this.results = [...this.originalResults].sort((a, b) => Number(b.priceValue) - Number(a.priceValue));
                } else {
                    this.results = [...this.originalResults];
                }
            },

            isCompared(result) {
                return this.compare.some((item) => item.id === result.id);
            },

            toggleCompare(result) {
                this.compareLimit = false;

                if (this.isCompared(result)) {
                    this.compare = this.compare.filter((item) => item.id !== result.id);
                    return;
                }

                if (this.compare.length >= 3) {
                    this.compareLimit = true;
                    return;
                }

                this.compare.push(result);
            },

            async submit() {
                const currentMessage = this.message.trim();

                if (!currentMessage || this.loading) {
                    return;
                }

                this.controller?.abort();
                this.controller = new AbortController();

                this.loading = true;
                this.error = '';
                this.limitReached = false;
                this.searched = false;
                this.compare = [];
                this.compareLimit = false;

                try {
                    const response = await fetch('{{ route('directory.assist') }}', {
                        method: 'POST',
                        signal: this.controller.signal,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ message: currentMessage, history: this.conversation.slice(-8) }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (response.status === 429) {
                        this.limitReached = true;
                        return;
                    }

                    if (!response.ok) {
                        throw new Error(data.message || 'The guided search is unavailable right now. Please try again.');
                    }

                    this.results = Array.isArray(data.results) ? data.results : [];
                    this.originalResults = [...this.results];
                    this.applied = data.applied || {};
                    this.questions = Array.isArray(data.questions) ? data.questions.slice(0, 2) : [];
                    this.sort = 'match';
                    this.searched = true;
                    this.message = '';

                    this.conversation.push(
                        { role: 'user', content: currentMessage },
                        { role: 'assistant', content: `Found ${this.results.length} published ${this.results.length === 1 ? 'option' : 'options'}.` },
                    );
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        this.error = error.message || 'Something went wrong. Please try again.';
                    }
                } finally {
                    this.loading = false;
                }
            },
        }));
    });
</script>
@endonce
