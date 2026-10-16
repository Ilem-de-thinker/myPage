{{--
    One business in the hub.

    The hover treatment is the whole design of this card, and it is deliberately
    two states rather than one:

      at rest  a solid, opaque card. A column of opaque cards reads as a list of
               businesses, which is what this is.
      on hover the card lifts and turns to glass — backdrop-blur, a translucent
               fill, and a border that glows in the brand colour. The blur is
               only legible against something, so it has to be earned by the
               column behind it rather than sitting there permanently.

    Rendered twice per marquee column (once visible, once inert for the loop), so
    everything takes a $page and an $interactive flag rather than assuming it is
    the only copy on screen.

    @var \App\Domains\Seller\Models\StorePage $page
    @var bool $interactive  false for the duplicated, inert copy
--}}
@php
    use App\Domains\Seller\Enums\Theme;

    $isService = $page->theme === Theme::Service;
    $where = $page->locationLabel();
    $url = route('storefront.show', ['slug' => $page->slug]);
@endphp

<article @class([
        'hub-card group relative flex flex-col overflow-hidden rounded-2xl border p-5',
        'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
        'transition-[transform,box-shadow,background-color,border-color] duration-300 ease-out',
        // The lift, the glass and the glow, all on the same transition so they
        // arrive together. Separated, the card looks like it is loading.
        'hover:-translate-y-1 hover:border-[var(--primary)]/45 hover:bg-white/70',
        'hover:shadow-[0_18px_45px_-18px_rgb(var(--brand-primary)/0.45),0_0_0_1px_rgb(var(--brand-primary)/0.12)]',
        'hover:backdrop-blur-md',
        'dark:hover:border-[var(--primary)]/50 dark:hover:bg-slate-900/55',
    ])
    {{-- Removed on the inert copy so it is not a second tab stop pointing at the
         same shop. --}}
    @if ($interactive) tabindex="0" @endif>

  {{-- A wash that only exists to give the glass something to blur. Without a
       backdrop behind the card, backdrop-blur renders as a flat tint and the
       effect is invisible. --}}
  <div aria-hidden="true"
       class="pointer-events-none absolute inset-0 -z-10 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
       style="background:
              radial-gradient(18rem 10rem at 50% 0%, rgb(var(--brand-primary) / 0.10), transparent 70%),
              radial-gradient(14rem 8rem at 20% 100%, rgb(249 115 22 / 0.08), transparent 70%)"></div>

  <div class="flex items-start justify-between gap-3">
    <div class="flex min-w-0 items-center gap-3">
      {{-- The initial, not a grey placeholder: a seller with no logo still gets
           something carrying their name rather than an empty box. --}}
      @if ($page->logo_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}" alt=""
             class="size-11 shrink-0 rounded-xl object-cover ring-1 ring-slate-900/5 dark:ring-white/10"
             loading="lazy" decoding="async">
      @else
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft font-display text-lg font-extrabold text-brand-dark ring-1 ring-black/5 dark:bg-brand-900/40 dark:text-emerald-200 dark:ring-white/10"
              aria-hidden="true">{{ mb_strtoupper(mb_substr($page->name, 0, 1)) }}</span>
      @endif

      <div class="min-w-0">
        <h3 class="flex items-center gap-1.5 truncate font-display text-[15px] font-bold text-ink dark:text-slate-100">
          <span class="truncate transition-colors duration-300 group-hover:text-[var(--primary)] dark:group-hover:text-emerald-300">{{ $page->name }}</span>
          <x-themes.shared.verified-badge :page="$page" />
        </h3>

        @if ($where)
          <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $where }}</p>
        @endif
      </div>
    </div>

    {{--
        The badge is the one thing a moving reader can actually process.

        Emoji and shape carry the distinction, not colour: a list scrolling past
        is not a place where a subtle shade difference survives being read at
        speed, and both badges are the same pill with a different glyph, a
        different tint and a different wording so none of the three is load-bearing
        on its own.
    --}}
    <span @class([
        'flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset',
        'bg-brand-soft text-brand-dark ring-brand/15 dark:bg-brand-900/40 dark:text-emerald-200 dark:ring-emerald-400/20' => ! $isService,
        'bg-indigo-50 text-indigo-700 ring-indigo-200/70 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-400/20' => $isService,
    ])>
      <span aria-hidden="true">{{ $isService ? '📅' : '🛍️' }}</span>
      {{ $isService ? 'Booking' : 'Store' }}
    </span>
  </div>

  @if ($page->tagline)
    <p class="mt-3 line-clamp-2 text-[13px] leading-relaxed text-slate-600 dark:text-slate-400">
      {{ $page->tagline }}
    </p>
  @endif

  <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5">
    {{-- The shared stars component, not a fourth hand-rolled star row. The count
         travels with the score inside it: one five-star review is not a
         consensus, and a bare 5.0 claims that it is. --}}
    @if ($page->hasRating())
      <x-ui.stars :rating="$page->rating" :count="$page->review_count" size="xs" />
    @endif

    @if ($page->price_tier)
      <span class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400" title="{{ $page->price_tier->description() }}">
        <span class="font-semibold tracking-tight text-brand-dark dark:text-emerald-300">{{ $page->price_tier->shortLabel() }}</span>
        {{ $page->price_tier->label() }}
      </span>
    @endif

    @if ($page->is_featured)
      <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200/70 ring-inset dark:bg-amber-400/15 dark:text-amber-200 dark:ring-amber-400/20">
        Featured
      </span>
    @endif
  </div>

  {{--
      The footer carries the CTA.

      The card itself is not one big link: a link wrapping the whole card makes
      the name, the badge and the tier a single tab stop announced as one long
      string. The text link below is the target, and the chevron repeats it as an
      obvious affordance for someone scanning the bottom edge.

      Rendered only on the interactive copy. On the inert duplicate it becomes a
      span: an aria-hidden link is still a link, and a keyboard trap dressed up as
      decoration is worse than a grey chevron.
  --}}
  <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 transition-colors duration-300 group-hover:border-[var(--primary)]/20 dark:border-slate-800 dark:group-hover:border-emerald-400/20">
    @if ($interactive)
      <a href="{{ $url }}"
         class="text-[13px] font-semibold text-[var(--primary)] transition-colors hover:underline dark:text-emerald-300">
        {{ $isService ? 'View services' : 'Browse the shop' }}
        <span class="sr-only"> — {{ $page->name }}</span>
      </a>

      <a href="{{ $url }}" aria-label="Open {{ $page->name }}"
         class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand-dark transition-all duration-300 group-hover:translate-x-0.5 group-hover:bg-[var(--primary)] group-hover:text-white dark:bg-brand-900/40 dark:text-emerald-200 dark:group-hover:bg-emerald-500 dark:group-hover:text-slate-900">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
        </svg>
      </a>
    @else
      <span class="text-[13px] font-semibold text-slate-400 dark:text-slate-600">
        {{ $isService ? 'View services' : 'Browse the shop' }}
      </span>
    @endif
  </div>
</article>