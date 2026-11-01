@props(['products' => collect(), 'services' => collect()])

@php
    $matches = collect();

    foreach ($products as $product) {
        $matches->push([
            'type' => 'product',
            'id' => $product->id,
            'name' => $product->name,
            'store' => $product->storePage,
            'price' => $product->priceLabel,
            'description' => $product->description,
        ]);
    }

    foreach ($services as $service) {
        $matches->push([
            'type' => 'service',
            'id' => $service->id,
            'name' => $service->name,
            'store' => $service->storePage,
            'price' => $service->priceLabel(),
            'description' => $service->description,
        ]);
    }

    $matches = $matches->take(3);
@endphp

<section id="help-me-buy-demo" class="relative overflow-hidden border-y border-[var(--platform-line)] bg-[var(--platform-ink)] py-20 text-[var(--platform-ink-invert)] lg:py-32"
         x-data="helpMeBuyShowcase"
         x-init="init()">

  <div class="pointer-events-none absolute inset-0" aria-hidden="true">
    <div class="absolute -right-40 -top-40 size-96 rounded-full bg-[var(--platform-support)]/15 blur-3xl"></div>
    <div class="absolute -bottom-40 -left-40 size-96 rounded-full bg-[var(--platform-accent)]/15 blur-3xl"></div>
    <div class="absolute inset-0 opacity-[0.08]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 26px 26px;"></div>
  </div>

  <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-8">
    <div class="grid items-center gap-12 lg:grid-cols-[1fr_1.15fr] lg:gap-16">
      <div data-reveal>
        <span class="inline-flex items-center gap-2 rounded-full border border-[color-mix(in_srgb,var(--platform-ink-invert)_15%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_5%,transparent)] px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--platform-accent-on-ink)]">
          Guided discovery
        </span>
        <h2 class="mt-5 font-display text-[clamp(2.5rem,5.5vw,4.5rem)] font-extrabold leading-[1.02] tracking-tight">
          Describe what you want. <br>
          <em class="font-normal italic text-[var(--platform-accent-on-ink)]">We search what is published.</em>
        </h2>
        <p class="mt-6 text-base leading-relaxed text-[color-mix(in_srgb,var(--platform-ink-invert)_65%,transparent)] sm:text-lg">
          Help me buy reads your request and searches real products, services and stores published across the Nigerian network. You continue to the merchant to ask, book or order.
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-4">
          <button type="button" @click="$dispatch('marketplace-open')" class="btn-magnetic inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[var(--platform-accent)] px-6 text-sm font-bold text-[var(--platform-ink-invert)] shadow-lg transition-all hover:bg-[var(--platform-accent-hover)]">
            <x-ui.icon name="spark" class="size-4" />
            <span>Try Help me buy</span>
          </button>
          <button type="button" @click="play()" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-[color-mix(in_srgb,var(--platform-ink-invert)_20%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_5%,transparent)] px-5 text-sm font-semibold text-[var(--platform-ink-invert)] transition-colors hover:bg-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)]">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
            <span>Replay demo</span>
          </button>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4 border-t border-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] pt-6 text-xs text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">
          <div>
            <strong class="block font-display text-lg text-[var(--platform-ink-invert)]">No checkout here</strong>
            <span>You pay the seller at their store.</span>
          </div>
          <div>
            <strong class="block font-display text-lg text-[var(--platform-ink-invert)]">Direct handoff</strong>
            <span>Ask, book or order one-on-one.</span>
          </div>
        </div>
      </div>

      <div data-reveal="scale" class="relative rounded-3xl border border-white/15 bg-neutral-900/90 p-5 shadow-2xl backdrop-blur-xl sm:p-7">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-lg bg-[var(--platform-accent)] text-[var(--platform-ink-invert)] shadow-sm">
              <x-ui.icon name="spark" class="size-4" />
            </div>
            <div>
              <span class="block text-xs font-bold text-white">MyPage Help Me Buy</span>
              <span class="block text-[10px] text-white/40">Grounded in published merchant listings</span>
            </div>
          </div>
          <span class="flex items-center gap-1.5 rounded-full border border-[color-mix(in_srgb,var(--platform-support-on-dark)_40%,transparent)] bg-[color-mix(in_srgb,var(--platform-support-on-dark)_15%,transparent)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--platform-support-on-dark)]">
            <span class="size-1.5 rounded-full bg-[var(--platform-support-on-dark)]"></span>
            Ready
          </span>
        </div>

        <div class="mt-5 rounded-2xl border border-white/10 bg-black/40 p-4">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--platform-accent-on-dark)]">Your request</span>
          <p class="mt-2 min-h-12 font-display text-base font-medium text-white sm:text-lg">
            <span x-text="query || 'Ask for a product, service or gift.'"></span>
            <span x-show="step === 1" class="ml-1 inline-block h-4 w-1.5 animate-pulse bg-[var(--platform-accent)]"></span>
          </p>
        </div>

        <div x-show="step === 2" x-transition.opacity class="my-6 rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
          <div class="inline-flex items-center gap-2 text-xs font-bold text-[var(--platform-accent-on-dark)]">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>Searching published catalogues…</span>
          </div>
          <p class="mt-1 text-[11px] text-white/40">Matching names, categories, descriptions and merchant locations.</p>
        </div>

        <div x-show="step === 3" x-transition.opacity class="mt-5 space-y-3">
          @if ($matches->isNotEmpty())
            <div class="flex items-center justify-between text-xs text-white/40">
              <span>{{ $matches->count() }} {{ \Illuminate\Support\Str::plural('published match', $matches->count()) }}</span>
              <span class="font-semibold text-[var(--platform-support-on-dark)]">Real merchant data</span>
            </div>

            @foreach ($matches as $match)
              <div class="rounded-xl border border-white/15 bg-neutral-800/90 p-4 transition-all hover:border-[var(--platform-support-on-dark)]">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <span class="text-[10px] font-bold uppercase text-[var(--platform-support-on-dark)]">
                      {{ $match['store']?->name ?? 'Published store' }}
                      @if ($match['store']?->locationLabel())
                        · {{ $match['store']->locationLabel() }}
                      @endif
                    </span>
                    <h4 class="mt-0.5 truncate font-display text-sm font-bold text-white">{{ $match['name'] }}</h4>
                    <p class="mt-1 line-clamp-2 text-xs text-white/50">{{ \Illuminate\Support\Str::limit(strip_tags($match['description']), 110) }}</p>
                  </div>
                  <span class="shrink-0 font-display text-sm font-extrabold text-[var(--platform-support-on-dark)]">{{ $match['price'] }}</span>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-3">
                  <span class="text-[10px] uppercase tracking-[0.1em] text-white/35">{{ $match['type'] === 'service' ? 'Service listing' : 'Product listing' }}</span>
                  <a href="{{ route('directory.go', ['type' => $match['type'], 'id' => $match['id'], 'source' => 'showcase']) }}" class="inline-flex items-center gap-1 text-xs font-bold text-[var(--platform-accent-on-dark)] hover:underline">
                    <span>View at merchant</span>
                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                  </a>
                </div>
              </div>
            @endforeach
          @else
            <div class="rounded-xl border border-dashed border-white/20 p-6 text-center">
              <p class="font-display text-base font-bold text-white">No published matches yet.</p>
              <p class="mt-1 text-xs leading-6 text-white/45">When merchants publish listings, Help me buy can search them. You can still browse the directory directly.</p>
              <a href="{{ route('directory.products') }}" class="mt-4 inline-flex h-10 items-center rounded-xl bg-white/10 px-4 text-xs font-bold text-white hover:bg-white/15">Browse published products</a>
            </div>
          @endif
        </div>

        <div class="mt-4 flex items-center justify-between rounded-xl bg-white/5 px-4 py-2.5 text-[11px] text-white/55">
          <span class="flex items-center gap-1.5">
            <svg class="size-3.5 text-[var(--platform-support-on-dark)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
            Direct with the merchant. No buyer account required.
          </span>
          <span class="text-[10px] uppercase tracking-wider text-white/35">Direct handoff</span>
        </div>
      </div>
    </div>
  </div>
</section>

@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('helpMeBuyShowcase', () => ({
            step: 1,
            query: '',
            sampleText: 'What you looking for?',
            timer: null,
            observer: null,

            init() {
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    || window.matchMedia('(prefers-reduced-data: reduce)').matches
                    || navigator.connection?.saveData === true;

                if (reduceMotion) {
                    this.query = this.sampleText;
                    this.step = 3;
                    return;
                }

                this.observer = new IntersectionObserver(([entry]) => {
                    if (entry.isIntersecting && this.step === 1 && ! this.query) {
                        this.play();
                        this.observer?.disconnect();
                    }
                }, { threshold: 0.35 });

                this.observer.observe(this.$el);
            },

            play() {
                window.clearInterval(this.timer);
                this.step = 1;
                this.query = '';
                let index = 0;

                this.timer = window.setInterval(() => {
                    if (index < this.sampleText.length) {
                        this.query += this.sampleText.charAt(index);
                        index++;
                        return;
                    }

                    window.clearInterval(this.timer);
                    window.setTimeout(() => {
                        this.step = 2;
                        window.setTimeout(() => {
                            this.step = 3;
                        }, 1200);
                    }, 400);
                }, 38);
            },
        }));
    });
</script>
@endonce
