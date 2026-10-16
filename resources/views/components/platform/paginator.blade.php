@props([
    // A LengthAwarePaginator. The design's page numbers are a fixed window around
    // the current page, which is what the component works out rather than
    // hard-coding seven links the way the static prototype did.
    'paginator',
    'label' => 'verified merchants',
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // First, last, and the two either side of where we are. Whatever is left
    // between a gap and its neighbour becomes an ellipsis.
    $window = collect([1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $last])
        ->filter(fn (int $page): bool => $page >= 1 && $page <= $last)
        ->unique()
        ->sort()
        ->values();

    $previousGap = false;
    $links = $window->map(function (int $page) use (&$previousGap) {
        $gap = $previousGap && $page > 1;

        $previousGap = true;

        return ['page' => $page, 'gap' => $gap];
    });
@endphp

<nav class="flex items-center justify-between border-t border-[var(--rule)] pt-6 mt-10" aria-label="Pagination">
    <div class="hidden sm:block">
        <p class="text-xs text-[var(--ink-3)]">
            Showing <span class="font-bold text-[var(--ink)] tabular-nums">{{ $paginator->firstItem() ?? 0 }}</span> to <span class="font-bold text-[var(--ink)] tabular-nums">{{ $paginator->lastItem() ?? 0 }}</span> of <span class="font-bold text-[var(--ink)] tabular-nums">{{ $paginator->total() }}</span> {{ $label }}
        </p>
    </div>
    <div class="flex items-center gap-1.5 w-full sm:w-auto justify-between sm:justify-end">
        @if ($paginator->onFirstPage())
            <span class="min-w-11 h-11 px-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--surface)] text-[var(--ink-3)] flex items-center justify-center text-xs font-semibold" aria-hidden="true">
                ← Prev
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="min-w-11 h-11 px-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] text-[var(--ink-3)] flex items-center justify-center text-xs font-semibold hover:border-[var(--brand-edge)] hover:text-[var(--ink)] transition-colors" rel="prev" aria-label="Previous page">
                ← Prev
            </a>
        @endif

        <div class="flex items-center gap-1">
            @foreach ($links as $link)
                @if ($link['gap'])
                    <span class="px-2 text-xs text-[var(--ink-3)] select-none">…</span>
                @endif
                <a href="{{ $paginator->url($link['page']) }}"
                   @class([
                       'min-w-11 h-11 px-3.5 rounded-[var(--radius)] flex items-center justify-center text-xs tabular-nums',
                       'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' => $link['page'] === $current,
                       'border border-[var(--rule)] bg-[var(--paper)] text-[var(--ink)] font-semibold hover:border-[var(--brand-edge)] transition-colors' => $link['page'] !== $current,
                   ])
                   @if ($link['page'] === $current) aria-current="page" @endif
                   aria-label="Page {{ $link['page'] }}">{{ $link['page'] }}</a>
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="min-w-11 h-11 px-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] text-[var(--ink)] flex items-center justify-center text-xs font-semibold hover:border-[var(--brand-edge)] transition-colors" rel="next" aria-label="Next page">
                Next →
            </a>
        @else
            <span class="min-w-11 h-11 px-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--surface)] text-[var(--ink-3)] flex items-center justify-center text-xs font-semibold" aria-hidden="true">
                Next →
            </span>
        @endif
    </div>
</nav>