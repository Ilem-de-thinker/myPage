@props(['reviews' => collect()])

<section class="relative overflow-hidden border-t border-[var(--platform-line)] bg-[var(--platform-paper)] py-20 lg:py-28">
  {{-- Section texture --}}
  <div class="pointer-events-none absolute inset-0" aria-hidden="true">
    <div class="absolute inset-0 opacity-25" style="background-image: radial-gradient(circle, var(--platform-line-strong) 1px, transparent 1px); background-size: 30px 30px;"></div>
    <div class="absolute -right-24 top-24 size-[24rem] rounded-full bg-[var(--platform-support)]/8 blur-3xl"></div>
    <div class="absolute -left-24 bottom-24 size-[22rem] rounded-full bg-[var(--platform-accent)]/8 blur-3xl"></div>
  </div>

  <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-8">
    {{-- Trust pillars --}}
    <div data-reveal class="border-b border-[var(--platform-line)] pb-16 lg:pb-20">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
          <span class="platform-kicker">What the directory does</span>
          <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-[var(--platform-ink)] sm:text-5xl">
            A clear handoff is the trust feature.
          </h2>
        </div>
        <p class="max-w-sm text-sm leading-7 text-[var(--platform-ink-muted)]">
          MyPage helps you find a published business. The introduction ends at the merchant’s own store, where they answer questions and handle the order.
        </p>
      </div>

      <div class="mt-10 grid gap-5 sm:grid-cols-3">
        @foreach ([
            [
                'title' => 'Published stores only',
                'body' => 'Every store shown here is published and has chosen to appear in the directory.',
                'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z',
            ],
            [
                'title' => 'Direct seller contact',
                'body' => 'Follow a listing to the merchant’s storefront, then ask, book or order with them directly.',
                'icon' => 'M21 11.5a8.4 8.4 0 0 1-9 8.5 9.7 9.7 0 0 1-4.2-1L3 21l1.5-4.1A8.4 8.4 0 0 1 3 11.5 8.5 8.5 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5Z',
            ],
            [
                'title' => 'Live merchant data',
                'body' => 'Names, prices and locations shown in search come from the merchant’s published catalogue.',
                'icon' => 'M12 6v6l4 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
            ],
        ] as $index => $pillar)
          <article data-reveal style="--stagger-index: {{ $index }}" class="rounded-[var(--platform-radius-xl)] border border-[var(--platform-line)] bg-[var(--platform-card)] p-6 shadow-[var(--platform-shadow-xs)] sm:p-7">
            <span @class([
                'flex size-11 items-center justify-center rounded-2xl',
                'bg-[var(--platform-support-wash)] text-[var(--platform-support)]' => $index === 0,
                'bg-[var(--platform-accent-wash)] text-[var(--platform-accent)]' => $index === 1,
                'bg-[var(--platform-surface)] text-[var(--platform-ink)]' => $index === 2,
            ])>
              <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $pillar['icon'] }}"/></svg>
            </span>
            <h3 class="mt-5 font-display text-xl font-bold text-[var(--platform-ink)]">{{ $pillar['title'] }}</h3>
            <p class="mt-2 text-sm leading-6 text-[var(--platform-ink-muted)]">{{ $pillar['body'] }}</p>
          </article>
        @endforeach
      </div>
    </div>

    {{-- Real featured reviews. No invented quotes, no stars without a rating. --}}
    <div class="border-b border-[var(--platform-line)] py-16 lg:py-20">
      <div data-reveal class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <span class="platform-kicker">Merchant reviews</span>
          <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-[var(--platform-ink)] sm:text-5xl">
            Words from published buyers.
          </h2>
        </div>
        @if ($reviews->isNotEmpty())
          <span class="rounded-full border border-[var(--platform-line)] bg-[var(--platform-card)] px-3 py-1 text-[10px] font-bold text-[var(--platform-ink-muted)]">
            {{ number_format($reviews->count()) }} featured
          </span>
        @endif
      </div>

      @if ($reviews->isNotEmpty())
        <div class="platform-marquee-wrap mt-8" data-pause-motion>
          <div class="platform-marquee-inner py-2">
            @foreach ($reviews as $review)
              <article class="mr-4 flex min-h-[15rem] w-[min(24rem,84vw)] shrink-0 flex-col justify-between gap-5 rounded-[var(--platform-radius-xl)] border border-[var(--platform-line)] bg-[var(--platform-card)] p-6 shadow-[var(--platform-shadow-sm)]">
                <div>
                  <div class="flex items-center gap-1.5 text-[var(--platform-accent)]" aria-label="{{ $review->rating }} out of 5 stars">
                    @for ($star = 1; $star <= 5; $star++)
                      <svg class="size-3.5" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M10 1.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8L10 14.9 4.7 17.6l1-5.8L1.5 7.7l5.9-.9L10 1.5Z" @class(['fill-current' => $star <= $review->rating, 'fill-none stroke-current opacity-35' => $star > $review->rating]) />
                      </svg>
                    @endfor
                  </div>
                  <p class="mt-4 font-display text-lg italic leading-7 text-[var(--platform-ink)]">“{{ strip_tags($review->body) }}”</p>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-[var(--platform-line)] pt-4">
                  <div class="min-w-0">
                    <strong class="block truncate text-xs font-bold text-[var(--platform-ink)]">{{ $review->authorLabel() }}</strong>
                    @if ($review->storePage)
                      <a href="{{ route('directory.store', $review->storePage) }}" class="mt-0.5 block truncate text-[11px] font-semibold text-[var(--platform-support)] hover:underline">{{ $review->storePage->name }}</a>
                    @endif
                  </div>
                  <span class="shrink-0 text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--platform-ink-faint)]">{{ $review->published_at?->format('M Y') }}</span>
                </div>
              </article>
            @endforeach
          </div>
        </div>
      @else
        <div class="mt-8 rounded-[var(--platform-radius-xl)] border border-dashed border-[var(--platform-line-strong)] bg-[var(--platform-card)] px-6 py-12 text-center">
          <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--platform-surface)] text-[var(--platform-ink-muted)]">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.7 9.7 0 0 1-4.2-1L3 21l1.5-4.1A8.4 8.4 0 0 1 3 11.5 8.5 8.5 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5Z"/></svg>
          </span>
          <h3 class="mt-4 font-display text-xl font-bold text-[var(--platform-ink)]">No featured reviews yet.</h3>
          <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[var(--platform-ink-muted)]">Approved reviews from buyers will appear here once merchants publish them. In the meantime, browse the live directory.</p>
          <a href="{{ route('directory.stores') }}" class="mt-5 inline-flex h-10 items-center rounded-xl bg-[var(--platform-ink)] px-5 text-xs font-bold text-[var(--platform-ink-invert)]">Browse published stores</a>
        </div>
      @endif
    </div>

    {{-- FAQ --}}
    <div class="border-b border-[var(--platform-line)] py-16 lg:py-20">
      <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
        <div data-reveal class="lg:sticky lg:top-24 lg:self-start">
          <span class="platform-kicker">Questions</span>
          <h2 class="mt-3 font-display text-3xl font-extrabold tracking-tight text-[var(--platform-ink)] sm:text-5xl">
            What buyers and sellers ask.
          </h2>
          <p class="mt-3 max-w-sm text-sm leading-7 text-[var(--platform-ink-muted)]">The short answers. If you need the merchant’s answer, use the contact route on their store.</p>
        </div>

        <div class="grid gap-3">
          @foreach ([
              ['Does MyPage sell the items or take my payment?', 'No. MyPage is a discovery directory. Once you find a business, you continue to the merchant’s own store to ask, book or pay them directly.'],
              ['Do I need a buyer account?', 'No. You can browse and search without registering. The relationship is between you and the merchant.'],
              ['How does Help me buy work?', 'It reads your request and searches published merchant listings. Names, prices and locations in the results come from those listings.'],
              ['Are the prices current?', 'Prices shown in the directory are read from the merchant’s catalogue. The merchant’s own store remains the source of truth.'],
              ['Can I contact the business directly?', 'Yes. A listing links to the merchant’s published store and, where they provided one, a direct WhatsApp route.'],
              ['How does a business get listed?', 'A seller publishes a storefront and opts in to the directory. Only published, opted-in stores appear in these results.'],
          ] as $index => $faq)
            <details data-reveal style="--stagger-index: {{ $index }}" class="group rounded-[var(--platform-radius-lg)] border border-[var(--platform-line)] bg-[var(--platform-card)] p-5 [&_summary::-webkit-details-marker]:hidden">
              <summary class="flex cursor-pointer items-center justify-between gap-4 font-display text-base font-bold text-[var(--platform-ink)] group-open:text-[var(--platform-support)]">
                <span>{{ $faq[0] }}</span>
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[var(--platform-surface)] text-sm font-extrabold transition-transform duration-300 group-open:rotate-45">+</span>
              </summary>
              <p class="mt-3 text-sm leading-6 text-[var(--platform-ink-muted)]">{{ $faq[1] }}</p>
            </details>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Final call --}}
    <div data-reveal class="relative mt-16 overflow-hidden rounded-[var(--platform-radius-2xl)] border border-[var(--platform-line)] bg-[var(--platform-paper-subtle)] p-8 text-center sm:p-14 lg:mt-20">
      <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true" style="background-image: radial-gradient(circle, var(--platform-line-strong) 1px, transparent 1px); background-size: 22px 22px;"></div>
      <div class="pointer-events-none absolute -right-20 -top-20 size-72 rounded-full bg-[var(--platform-support)]/10 blur-3xl" aria-hidden="true"></div>
      <div class="relative z-10">
        <span class="platform-kicker">The Nigerian directory</span>
        <h2 class="mx-auto mt-4 max-w-3xl font-display text-4xl font-extrabold leading-[0.98] tracking-tight text-[var(--platform-ink)] sm:text-6xl">
          Find the place that gets it.<br>
          <span class="text-[var(--platform-support)]">Or publish your own store.</span>
        </h2>
        <p class="mx-auto mt-5 max-w-xl text-sm leading-7 text-[var(--platform-ink-muted)] sm:text-base">
          Browse the live directory, or create a storefront and choose whether to appear in it.
        </p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
          <a href="{{ route('directory.stores') }}" class="btn-magnetic inline-flex h-12 items-center justify-center rounded-xl bg-[var(--platform-ink)] px-7 text-sm font-bold text-[var(--platform-ink-invert)] shadow-[var(--platform-shadow-md)]">Explore the directory</a>
          <a href="{{ route('register') }}" class="btn-magnetic inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[var(--platform-support)] px-7 text-sm font-bold text-white shadow-[var(--platform-shadow-md)]">
            <span>Create your store</span>
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
