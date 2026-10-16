@props([
    'reviews',
    'rating' => null,
    'count' => 0,
    'heading' => 'Reviews',
    // False when the list is embedded inside a section that already owns the
    // border and the spacing — the home page's reviews block. A nested <section>
    // with its own rule and mt-12 reads as two components stacked by accident.
    'framed' => true,
    // The heading level follows the page it is dropped into, so an embedded list
    // does not put a second <h2> inside its parent's <h2>.
    'level' => 2,
])

{{--
    The published half of a reviewable's reviews.

    Only ever handed rows that are already approved. That filtering happens in
    the query (Review::published()), not here — a view that has to remember to
    check a status is a view that will eventually forget, and the failure is a
    seller's competitor appearing on their page.
--}}
@php $tag = 'h'.min(6, max(2, $level)); @endphp
<{{ $framed ? 'section' : 'div' }} @if ($framed) aria-labelledby="reviews-heading" @endif
     @class(['mt-12' => $framed])>

    <div @class([
        'flex flex-wrap items-center justify-between gap-3',
        'border-b border-[var(--rule)] pb-4 dark:border-slate-800' => $framed,
    ])>
        <{{ $tag }} @if ($framed) id="reviews-heading" @endif
               class="font-[family-name:var(--font-heading-stack)] {{ $framed ? 'text-xl' : 'text-2xl' }} font-extrabold tracking-[-0.02em]">
            {{ $heading }}
            @if ($count > 0)
                <span class="t-meta font-normal">({{ $count }})</span>
            @endif
        </{{ $tag }}>

        <x-ui.stars :rating="$rating" :count="$count" size="md" />
    </div>

    {{-- Nothing yet. Invites the first one instead of showing an empty star row,
         which reads as a broken widget rather than a new listing. --}}
    @forelse ($reviews as $review)
        <figure class="flex gap-4 border-b border-[var(--rule)] py-6 last:border-b-0 dark:border-slate-800">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--brand-wash)] text-sm font-bold"
                 style="color: var(--brand-text)"
                 aria-hidden="true">
                {{ mb_strtoupper(mb_substr($review->authorLabel(), 0, 1)) }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="text-[15px] font-semibold">{{ $review->authorLabel() }}</span>

                    <x-ui.stars :rating="$review->rating" :count="$review->rating" size="xs" :show-count="false" />

                    @if ($review->is_featured)
                        <span class="rounded-full border border-[var(--brand-edge)] px-2 py-0.5 text-[10px] font-semibold">
                            Seller's pick
                        </span>
                    @endif

                    <time class="t-meta ml-auto shrink-0"
                          datetime="{{ $review->published_at?->toDateString() }}">
                        {{ $review->published_at?->diffForHumans() }}
                    </time>
                </div>

                <blockquote class="t-body mt-2 whitespace-pre-line text-[color:var(--ink-2)]">
                    {{ $review->body }}
                </blockquote>
            </div>
        </figure>
    @empty
        <p class="t-body py-8 text-[color:var(--ink-2)]">
            No reviews yet. If you have bought here, yours would be the first.
        </p>
    @endforelse
</{{ $framed ? 'section' : 'div' }}>