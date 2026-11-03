@extends('layouts.app')

@section('content')
    @php
        $pending = $counts['pending'] ?? 0;
    @endphp

    <x-ui.page-header
        title="Reviews"
        subtitle="Everything a customer writes about your page arrives here first. Nothing appears on your site until you publish it."
        width="max-w-4xl mx-auto"
    />

    {{--
        The pending queue is the screen's whole reason to exist, so it is the
        default tab and it is the one that carries an unread count. A seller who
        opens this page once a week should not have to check three tabs to find
        out whether someone has written something rude.
    --}}
    <div class="mx-auto mb-6 flex max-w-4xl gap-1 overflow-x-auto rounded-xl bg-tint p-1">
        @foreach ($statuses as $case)
            <a href="{{ route('reviews.index', ['status' => $case->value]) }}"
               class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold transition-colors
                      {{ $status === $case ? 'bg-card text-ink shadow-sm' : 'text-ink-3 hover:text-ink' }}">

                {{ $case->label() }}

                @if (($counts[$case->value] ?? 0) > 0)
                    <span @class([
                        'rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums',
                        'bg-brand text-white' => $case === \App\Domains\Seller\Enums\ReviewStatus::Pending,
                        'bg-tint text-ink-2' => $case !== \App\Domains\Seller\Enums\ReviewStatus::Pending,
                    ])>{{ $counts[$case->value] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if (session('status'))
        <div class="mx-auto mb-4 max-w-4xl rounded-xl border border-ok-edge bg-ok-wash px-4 py-3 text-sm text-ok-text">
            {{ session('status') }}
        </div>
    @endif

    <div class="mx-auto max-w-4xl">
        @forelse ($reviews as $review)
            <article class="rounded-2xl border border-rule bg-card p-5 mb-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            {{-- The rating is what the seller is deciding on, so it
                                 is the most prominent thing on the row rather than
                                 a footnote under the name. --}}
                            <x-ui.stars
                                :rating="$review->rating"
                                :count="$review->rating"
                                size="sm"
                                :show-count="false" />

                            <span class="text-sm font-semibold text-ink">{{ $review->authorLabel() }}</span>

                            @if ($review->is_anonymous)
                                <span class="rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold text-ink-2">
                                    Anonymous
                                </span>
                            @endif

                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $review->status->badgeClasses() }}">
                                {{ $review->status->label() }}
                            </span>
                        </div>

                        @if ($subject = $review->subjectLabel())
                            <p class="mt-1 text-xs text-ink-3">
                                On <span class="font-medium">{{ $subject }}</span>
                            </p>
                        @endif

                        <p class="mt-1 text-[11px] text-ink-3">
                            {{ $review->created_at->diffForHumans() }}
                        </p>
                    </div>

                    {{-- Actions are on the right on desktop and wrap beneath on a
                         phone, because a three-button row and a long review text
                         do not share a line at 375px. --}}
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if ($review->isPending())
                            <form method="POST" action="{{ route('reviews.approve', $review) }}">
                                @csrf
                                <button class="min-h-10 rounded-lg bg-brand px-4 text-xs font-semibold text-white hover:bg-brand-hover">
                                    Publish
                                </button>
                            </form>

                            <form method="POST" action="{{ route('reviews.reject', $review) }}">
                                @csrf
                                <button class="min-h-10 rounded-lg border border-rule px-4 text-xs font-semibold text-ink-2 hover:bg-surface">
                                    Decline
                                </button>
                            </form>
                        @elseif ($review->isPublished())
                            <form method="POST" action="{{ route('reviews.feature', $review) }}">
                                @csrf
                                <button class="min-h-10 rounded-lg border px-4 text-xs font-semibold
                                    {{ $review->is_featured ? 'border-brand bg-brand-wash text-brand-text' : 'border-rule text-ink-2 hover:bg-surface' }}">
                                    {{ $review->is_featured ? 'Featured' : 'Feature' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- The body is sanitised on write, but a moderator is about to
                     decide whether it goes public, so it is rendered as plain
                     text with whitespace preserved rather than as HTML. --}}
                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-ink">
                    {{ $review->body }}
                </p>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-rule-strong bg-card px-6 py-16 text-center">
                <p class="text-sm font-semibold text-ink">
                    @if ($status === \App\Domains\Seller\Enums\ReviewStatus::Pending)
                        {{ $pending > 0 ? 'Nothing waiting.' : 'No reviews waiting.' }}
                    @else
                        Nothing here yet.
                    @endif
                </p>

                <p class="mx-auto mt-2 max-w-sm text-xs leading-relaxed text-ink-3">
                    @if ($status === \App\Domains\Seller\Enums\ReviewStatus::Pending)
                        When a customer writes a review it appears here, with nothing
                        published until you say so.
                    @else
                        Reviews will show up here once they have been published.
                    @endif
                </p>

                @if ($pending > 0 && $status !== \App\Domains\Seller\Enums\ReviewStatus::Pending)
                    <a href="{{ route('reviews.index', ['status' => 'pending']) }}"
                       class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-ink px-5 text-xs font-semibold text-white">
                        Review {{ $pending }} waiting
                    </a>
                @endif
            </div>
        @endforelse

        @if ($reviews->hasPages())
            <div class="mt-6">{{ $reviews->links() }}</div>
        @endif
    </div>
@endsection