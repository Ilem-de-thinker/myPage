@props(['product' => null, 'store' => null])

{{-- Sticky discovery story: content remains useful without JavaScript. --}}
<section id="how-it-works" class="platform-how-section relative overflow-hidden border-y border-[var(--platform-line)] bg-[var(--platform-surface)] py-16 text-[var(--platform-ink)] lg:py-24">
  {{-- Atmospheric layers --}}
  <div class="pointer-events-none absolute inset-0" aria-hidden="true">
    <div class="absolute inset-0 opacity-[0.28]" style="background-image: radial-gradient(circle, var(--platform-line-strong) 1px, transparent 1px); background-size: 26px 26px;"></div>
    <div class="absolute -left-32 top-1/4 size-[30rem] rounded-full bg-[var(--platform-support)]/10 blur-3xl"></div>
    <div class="absolute -right-24 bottom-0 size-[26rem] rounded-full bg-[var(--platform-accent)]/10 blur-3xl"></div>
    <div class="absolute -right-10 top-24 font-display text-[clamp(8rem,22vw,21rem)] font-extrabold leading-none tracking-[-0.08em] text-transparent opacity-40" style="-webkit-text-stroke: 1px color-mix(in srgb, var(--platform-ink) 12%, transparent);">GO</div>
    <svg class="absolute bottom-16 left-6 hidden h-48 w-[28rem] opacity-50 lg:block" viewBox="0 0 520 220" fill="none">
      <path d="M10 190 C 120 190, 130 30, 250 30 S 410 170, 510 80" class="platform-story-route" stroke="var(--platform-accent)" stroke-width="1.5" />
      <circle cx="10" cy="190" r="5" fill="var(--platform-support)" />
      <circle cx="510" cy="80" r="6" fill="var(--platform-accent)" />
    </svg>
  </div>

  <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-8">
    <div class="flex flex-col gap-6 border-b border-[var(--platform-line)] pb-8 sm:flex-row sm:items-end sm:justify-between">
      <div class="max-w-3xl" data-reveal="mask-line">
        <p class="platform-kicker">A clearer way to buy</p>
        <h2 class="mt-4 max-w-3xl font-display text-[clamp(2.4rem,6vw,5.25rem)] font-extrabold leading-[.92] tracking-[var(--platform-tracking-display)]">
          <span>Find it fast.</span><br>
          <span class="font-normal italic text-[var(--platform-support)]">Then go straight to the seller.</span>
        </h2>
      </div>
      <p class="max-w-sm text-sm leading-7 text-[var(--platform-ink-muted)] sm:pb-2">
        MyPage is a discovery desk for Nigeria’s independent businesses. We make the introduction; the merchant handles the conversation, booking or order on their own store.
      </p>
    </div>

    <div class="mt-10 grid gap-8 lg:mt-12 lg:grid-cols-[1.02fr_0.98fr] lg:items-start lg:gap-14">
      {{-- Sticky visual --}}
      <div class="order-first lg:order-last lg:sticky lg:top-24">
        <div class="platform-story-visual-shell relative aspect-[4/5] overflow-hidden rounded-[var(--platform-radius-2xl)] border border-[var(--platform-line)] bg-[var(--platform-paper)] shadow-[var(--platform-shadow-lg)] sm:aspect-[5/4] lg:aspect-[4/5]">
          <div class="absolute inset-0 opacity-40" aria-hidden="true" style="background-image: radial-gradient(circle, var(--platform-line-strong) 1px, transparent 1px); background-size: 22px 22px;"></div>
          <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-70" viewBox="0 0 600 620" fill="none" aria-hidden="true" preserveAspectRatio="none">
            <path d="M70 118C210 40 236 334 348 242S456 146 528 470" stroke="var(--platform-accent)" stroke-width="1.6" stroke-dasharray="6 8" class="platform-story-route" />
            <circle cx="70" cy="118" r="6" fill="var(--platform-support)" />
            <circle cx="528" cy="470" r="7" fill="var(--platform-accent)" />
          </svg>

          {{-- 01 Describe --}}
          <div data-story-visual="1" class="story-visual-frame absolute inset-3 z-10 flex flex-col justify-between rounded-[var(--platform-radius-xl)] bg-[var(--platform-ink)] p-6 text-[var(--platform-ink-invert)] shadow-xl sm:inset-5 sm:p-8">
            <div>
              <span class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-[var(--platform-accent-on-ink)]">01 / Describe</span>
              <h3 class="mt-3 max-w-xs font-display text-3xl font-bold leading-tight sm:text-4xl">Start with the occasion.</h3>
            </div>
            <div class="rounded-[var(--platform-radius-lg)] border border-[color-mix(in_srgb,var(--platform-ink-invert)_15%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_5%,transparent)] p-5">
              <p class="text-sm leading-7 text-[color-mix(in_srgb,var(--platform-ink-invert)_75%,transparent)]">“A birthday gift for my mum, from a published Nigerian store.”</p>
              <div class="mt-4 flex items-center gap-2 border-t border-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] pt-4 text-[11px] font-semibold text-[color-mix(in_srgb,var(--platform-ink-invert)_50%,transparent)]">
                <span class="size-1.5 rounded-full bg-[var(--platform-accent)]"></span>
                <span>One sentence is enough</span>
              </div>
            </div>
          </div>

          {{-- 02 Compare --}}
          <div data-story-visual="2" class="story-visual-frame pointer-events-none absolute inset-3 z-10 flex flex-col justify-between rounded-[var(--platform-radius-xl)] bg-[var(--platform-paper)] p-6 opacity-0 shadow-xl sm:inset-5 sm:p-8" style="transform: scale(.95);">
            <div>
              <span class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-[var(--platform-accent)]">02 / Compare</span>
              <h3 class="mt-3 max-w-xs font-display text-3xl font-bold leading-tight text-[var(--platform-ink)] sm:text-4xl">See the useful differences.</h3>
            </div>
            @if ($product)
              <div class="rounded-[var(--platform-radius-lg)] border border-[var(--platform-line)] bg-[var(--platform-card)] p-5">
                <span class="text-xs font-semibold text-[var(--platform-ink-muted)]">{{ $product->storePage?->name }}</span>
                <strong class="mt-1 block font-display text-xl font-bold text-[var(--platform-ink)]">{{ $product->name }}</strong>
                <div class="mt-4 flex items-center justify-between border-t border-[var(--platform-line)] pt-4">
                  <span class="text-xs text-[var(--platform-ink-muted)]">Published price</span>
                  <strong class="font-display text-lg font-bold text-[var(--platform-ink)]">{{ $product->priceLabel }}</strong>
                </div>
              </div>
            @else
              <div class="rounded-[var(--platform-radius-lg)] border border-dashed border-[var(--platform-line-strong)] bg-[var(--platform-card)] p-5">
                <span class="text-xs font-semibold text-[var(--platform-ink-muted)]">Published listings</span>
                <p class="mt-2 text-sm leading-6 text-[var(--platform-ink-muted)]">A matched product appears here once a merchant publishes it to the directory.</p>
              </div>
            @endif
          </div>

          {{-- 03 Visit --}}
          <div data-story-visual="3" class="story-visual-frame pointer-events-none absolute inset-3 z-10 flex flex-col justify-between rounded-[var(--platform-radius-xl)] bg-[var(--platform-support)] p-6 text-[var(--platform-ink-invert)] opacity-0 shadow-xl sm:inset-5 sm:p-8" style="transform: scale(.95);">
            <div>
              <span class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-[color-mix(in_srgb,var(--platform-ink-invert)_60%,transparent)]">03 / Visit</span>
              <h3 class="mt-3 max-w-xs font-display text-3xl font-bold leading-tight sm:text-4xl">Follow the handoff.</h3>
            </div>
            @if ($store)
              <div class="rounded-[var(--platform-radius-lg)] bg-[var(--platform-paper)] p-5 text-[var(--platform-ink)]">
                <span class="text-xs text-[var(--platform-ink-muted)]">Leaving MyPage</span>
                <strong class="mt-1 block font-display text-xl font-bold">{{ $store->name }}</strong>
                <span class="mt-1 block text-xs text-[var(--platform-ink-muted)]">{{ $store->locationLabel() ?? 'Published Nigerian store' }}</span>
                <span class="mt-4 inline-flex items-center gap-1.5 border-t border-[var(--platform-line)] pt-4 text-xs font-bold text-[var(--platform-support)]">Open merchant store ↗</span>
              </div>
            @else
              <div class="rounded-[var(--platform-radius-lg)] bg-[var(--platform-paper)] p-5 text-[var(--platform-ink)]">
                <span class="text-xs text-[var(--platform-ink-muted)]">Leaving MyPage</span>
                <strong class="mt-1 block font-display text-xl font-bold">The merchant’s own store</strong>
                <span class="mt-4 inline-flex items-center gap-1.5 border-t border-[var(--platform-line)] pt-4 text-xs font-bold text-[var(--platform-support)]">Open merchant store ↗</span>
              </div>
            @endif
          </div>

          {{-- 04 Deal directly --}}
          <div data-story-visual="4" class="story-visual-frame pointer-events-none absolute inset-3 z-10 flex flex-col justify-between rounded-[var(--platform-radius-xl)] bg-[var(--platform-accent)] p-6 text-[var(--platform-ink-invert)] opacity-0 shadow-xl sm:inset-5 sm:p-8" style="transform: scale(.95);">
            <div>
              <span class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-[color-mix(in_srgb,var(--platform-ink-invert)_65%,transparent)]">04 / Deal directly</span>
              <h3 class="mt-3 max-w-xs font-display text-3xl font-bold leading-tight sm:text-4xl">Keep the relationship human.</h3>
            </div>
            <div class="rounded-[var(--platform-radius-lg)] border border-[color-mix(in_srgb,var(--platform-ink-invert)_25%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] p-5 text-sm leading-7 text-[color-mix(in_srgb,var(--platform-ink-invert)_90%,transparent)]">
              Ask, book or order with the seller on their own terms. MyPage makes the introduction; the merchant owns the conversation.
            </div>
          </div>
        </div>
      </div>

      {{-- Steps --}}
      <div class="order-last space-y-3 lg:space-y-4">
        @foreach ([
          ['1', 'Describe what you want', 'Start with a need, a budget, a city or an occasion. Search naturally or ask Help me buy.'],
          ['2', 'Compare real options', 'Look at published prices, locations and details from stores that chose to be discoverable.'],
          ['3', 'Visit the store', 'Follow the result to the merchant’s own MyPage, with a direct handoff and no platform checkout.'],
          ['4', 'Order directly with the seller', 'Ask questions, book or order on the seller’s own flow, where they handle the relationship.'],
        ] as $step)
          <article data-story-step="{{ $step[0] }}" data-reveal class="platform-story-step-card rounded-[var(--platform-radius-xl)] border border-[var(--platform-line)] bg-[var(--platform-card)] p-5 shadow-[var(--platform-shadow-xs)] transition sm:p-6">
            <div class="flex items-start gap-4">
              <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[var(--platform-ink)] font-display text-xs font-bold text-[var(--platform-ink-invert)]">0{{ $step[0] }}</span>
              <div>
                <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--platform-accent)]">Step {{ $step[0] }}</p>
                <h3 class="mt-1.5 font-display text-xl font-bold text-[var(--platform-ink)] sm:text-2xl">{{ $step[1] }}</h3>
                <p class="mt-2 max-w-lg text-sm leading-6 text-[var(--platform-ink-muted)]">{{ $step[2] }}</p>
              </div>
            </div>
          </article>
        @endforeach
      </div>
    </div>
  </div>
</section>
