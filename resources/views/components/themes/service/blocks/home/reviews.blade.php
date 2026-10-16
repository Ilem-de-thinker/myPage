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
    $heading = $block->field('heading') ?: 'What clients say';
@endphp

@if ($realReviews->isNotEmpty() || $typed !== [])
  <section class="section-y border-b border-[var(--rule)]">
    <div class="shell">
      <div class="text-center max-w-2xl mx-auto mb-12">
        <p class="t-meta text-[var(--brand-text)] font-semibold">Verified Testimonials</p>
        <h2 class="t-display-2 text-[var(--ink)] mt-1">{{ $heading }}</h2>

        {{-- The page's own score, but only once there is one to quote. --}}
        @if ($page->hasRating())
          <div class="mt-4 flex items-center justify-center gap-2">
            <x-ui.stars :rating="$page->rating" :count="$page->review_count" size="sm" />
            <span class="t-meta">{{ number_format($page->ratingLabel(), 1) }} from {{ $page->review_count }} {{ \Illuminate\Support\Str::plural('review', $page->review_count) }}</span>
          </div>
        @endif
      </div>

      @if ($realReviews->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          @foreach ($realReviews->take(3) as $review)
            <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-3">
              <x-ui.stars :rating="$review->rating" :count="$review->rating" size="xs" :show-count="false" />

              <p class="t-body text-xs text-[var(--ink-2)] italic leading-relaxed">{{ $review->body }}</p>

              <div class="pt-2 border-t border-[var(--rule)]">
                <p class="text-xs font-bold text-[var(--ink)]">{{ $review->authorLabel() }}</p>

                @if ($review->subjectLabel())
                  <p class="t-meta">{{ $review->subjectLabel() }} · Verified Client</p>
                @endif
              </div>
            </div>
          @endforeach
        </div>

        {{-- More than three fit in no grid, so the rest go in a plain list below. --}}
        @if ($realReviews->count() > 3)
          <div class="mt-10">
            <x-ui.review-list
                :framed="false"
                :level="3"
                :reviews="$realReviews->slice(3)"
                :rating="$page->rating"
                :count="$page->review_count - 3"
                :heading="'More from recent clients'" />
          </div>
        @endif
      @endif

      @if ($typed !== [])
        {{--
          Kept, and labelled. Dropping it would take a seller's real copy with it;
          calling it "reviews" without qualification would be the problem.
        --}}
        <div class="mt-12">
          <h3 class="t-display-2 text-[var(--ink)] text-center">
            In their words
            <span class="t-meta ml-2 font-normal">written by {{ $page->name }}</span>
          </h3>

          <div class="grid gap-x-10 gap-y-8 mt-8 md:grid-cols-3">
            @foreach ($typed as $item)
              <figure class="t-measure-narrow border-l-2 pl-6" style="border-color: var(--brand)">
                <blockquote class="t-body-lg font-[family-name:var(--font-heading-stack)] font-semibold tracking-[-0.01em]">
                  {{ $item['quote'] }}
                </blockquote>

                @if (! empty($item['author']))
                  <figcaption class="t-meta mt-3">{{ $item['author'] }}</figcaption>
                @endif
              </figure>
            @endforeach
          </div>
        </div>
      @endif
    </div>
  </section>
@endif