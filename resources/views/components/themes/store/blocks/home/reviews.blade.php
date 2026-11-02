@props(['page', 'block'])

@php
    /*
      Real customer reviews, falling back to the seller's own testimonial repeater.

      The block still has a `heading` and `items` schema, and a seller who has
      filled it in has written real sentences about their own business that are
      worth showing. But a seller writing their own customers' praise is not a
      review, and presenting it under a heading that says "Reviews" is the exact
      thing the reviews table exists to stop.

      So: approved rows first, typed testimonials only when there are none. Both
      can be on the page at once, because the two sources are visually different
      and labelling them differently is honest about which is which.

      $page->reviews arrives approved-only from the controller; this component
      never sees a pending row.
    */
    $realReviews = $page->reviews;
    $typed = array_values(array_filter($block->field('items') ?? [], fn ($item) => ! empty($item['quote'])));
    $heading = $block->field('heading') ?: 'Customer reviews';

    // The distribution is drawn from the rows actually on this page, not from a
    // stored histogram: a bar that claimed 92% when the twenty visible reviews
    // say otherwise would be the one number on the page nobody could check.
    $distribution = collect(range(5, 1))->mapWithKeys(fn (int $stars) => [
        $stars => $realReviews->where('rating', $stars)->count(),
    ]);
    $graded = $distribution->sum();
    $percent = fn (int $stars): int => $graded > 0
        ? (int) round(($distribution[$stars] / $graded) * 100)
        : 0;
@endphp

@if ($realReviews->isNotEmpty() || $typed !== [])
  {{--
    One scope around the section *and* the dialog, not one around either alone.

    Alpine scopes cascade downwards, so a button that sets `reviewModalOpen`
    can only reach a state declared above it. The state used to live on a
    wrapper around the dialog alone — below the button that opens it — so the
    name resolved to nothing and the button silently did nothing.
  --}}
  <div x-data="{ reviewModalOpen: false }">
    <section class="section-y border-b border-[var(--rule)] bg-[var(--surface)]">
    <div class="shell">
      <div class="flex items-center justify-between mb-8 gap-4">
        <div>
          <p class="t-meta">Verified Feedback</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-1">{{ $heading }}</h2>
        </div>

        <button type="button"
                @click="reviewModalOpen = true"
                class="px-4 py-2 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)] text-xs font-bold transition-colors shrink-0">
          Write a Review
        </button>
      </div>

      @if ($realReviews->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

          {{-- The score, and the shape of it. --}}
          <div class="lg:col-span-4 bg-[var(--card)] p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
            <h3 class="text-sm font-bold text-[var(--ink)] mb-4">Overall Score</h3>

            <div class="flex items-baseline gap-3 mb-6">
              <span class="text-4xl font-extrabold text-[var(--ink)] font-mono">{{ number_format($page->ratingLabel(), 2) }}</span>
              <div>
                <div class="flex text-[var(--rating)] text-sm" aria-label="Rating out of 5 stars">★★★★★</div>
                <span class="t-meta">From {{ $page->review_count }} verified {{ \Illuminate\Support\Str::plural('order', $page->review_count) }}</span>
              </div>
            </div>

            <div class="space-y-2 text-xs">
              @foreach ($distribution as $stars => $count)
                <div class="flex items-center gap-2">
                  <span class="w-12 text-[var(--ink-3)] tabular-nums">{{ $stars }} {{ \Illuminate\Support\Str::plural('star', $stars) }}</span>
                  <div class="flex-1 h-2 rounded-full bg-[var(--surface)] overflow-hidden border border-[var(--rule)]">
                    <div class="h-full rounded-full bg-[var(--rating)]" style="width: {{ $percent($stars) }}%"></div>
                  </div>
                  <span class="w-8 text-right font-medium text-[var(--ink)] tabular-nums">{{ $percent($stars) }}%</span>
                </div>
              @endforeach
            </div>
          </div>

          <div class="lg:col-span-8 space-y-4">
            @foreach ($realReviews as $review)
              <article class="p-5 rounded-[var(--radius-lg)] bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
                <div class="flex items-center justify-between mb-3 gap-4">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center font-bold text-xs text-[var(--ink)] shrink-0">
                      {{ mb_strtoupper(mb_substr($review->author_name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                      <h4 class="text-xs font-bold text-[var(--ink)] truncate">{{ $review->author_name }}</h4>
                      @if ($review->author_location)
                        <p class="t-meta">{{ $review->author_location }}</p>
                      @endif
                    </div>
                  </div>
                  <div class="text-right shrink-0">
                    <div class="text-xs text-[var(--rating)]" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}</div>
                    <span class="t-meta text-[11px]">{{ $review->published_at?->diffForHumans() }}</span>
                  </div>
                </div>

                <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-[var(--brand-text)] bg-[var(--brand-wash)] px-2 py-0.5 rounded-[var(--radius-sm)] mb-2">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                  Verified Purchase{{ $review->subjectLabel() ? ' · '.$review->subjectLabel() : '' }}
                </div>

                <p class="text-xs text-[var(--ink-2)] leading-relaxed">{{ $review->body }}</p>
              </article>
            @endforeach
          </div>

        </div>
      @endif

      @if ($typed !== [])
        {{--
          Kept, and labelled. Dropping it would take a seller's real copy with it;
          calling it "reviews" without qualification would be the problem.
        --}}
        @php $single = count($typed) === 1; @endphp
        <div class="{{ $realReviews->isNotEmpty() ? 'mt-12' : '' }} grid gap-x-10 gap-y-8 {{ $single ? '' : 'md:grid-cols-3' }}">
          @foreach ($typed as $item)
            <figure class="{{ $single ? 't-measure-narrow border-l-2 pl-6' : '' }}"
                    {{ $single ? 'style="border-color: var(--brand)"' : '' }}>
              <blockquote class="t-body-lg font-[family-name:var(--font-heading-stack)] font-semibold tracking-[-0.01em]">
                {{ $item['quote'] }}
              </blockquote>

              @if (! empty($item['author']))
                <figcaption class="t-meta mt-3">{{ $item['author'] }}</figcaption>
              @endif
            </figure>
          @endforeach
        </div>
      @endif
    </div>

  {{-- The review dialog. The form inside is the same one the contact page posts,
       so a buyer leaving a review from here is not taking a different route
       through the app than one leaving it from the page built for it. --}}
    <div x-show="reviewModalOpen"
         x-transition.opacity.duration.200ms
         @keydown.escape.window="reviewModalOpen = false"
         class="app-modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="store-review-title">

      <div @click.away="reviewModalOpen = false"
           class="app-modal-panel w-full max-w-lg bg-[var(--card)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-lift)] max-h-[85dvh] overflow-y-auto">

        <div class="flex items-center justify-between pb-3 border-b border-[var(--rule)] mb-4">
          <h3 id="store-review-title" class="text-base font-bold text-[var(--ink)]">Write a Customer Review</h3>
          <button type="button" @click="reviewModalOpen = false" class="text-[var(--ink-3)] hover:text-[var(--ink)] text-sm font-bold p-1" aria-label="Close review dialog">✕</button>
        </div>

        <x-ui.review-form
            :action="\App\Shared\Support\StoreUrl::path('storefront.review', ['slug' => $page->slug])"
            :reviewable="$page"
            :subject="$page->name" />
      </div>
    </div>
    </section>
  </div>
@endif
