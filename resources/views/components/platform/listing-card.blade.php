@props([
    'page',
    // False for the duplicated, inert copy the marquee appends to close the loop.
    'interactive' => true,
])

@php
    use App\Domains\Seller\Enums\Theme;
    use App\Shared\Support\StoreUrl;

    $isService = $page->theme === Theme::Service;
    $origin = $page->city ?: $page->state;
    $summary = $page->tagline ?: $page->bio;
@endphp

<article class="card p-4">
    <span class="t-meta">{{ $origin ? $origin.' · ' : '' }}{{ $page->theme->label() }}</span>
    <h3 class="text-sm font-bold text-[var(--ink)] mt-1">
        @if ($interactive)
            <a href="{{ StoreUrl::home($page) }}" class="card-stretched-link hover:underline">{{ $page->name }}</a>
        @else
            {{ $page->name }}
        @endif
    </h3>
    @if ($summary)
        <p class="text-xs text-[var(--ink-2)] mt-1">{{ $summary }}</p>
    @endif

    {{-- The footer is dropped on the inert copy: it is the only part that is not
         duplicated between the two halves of the loop. --}}
    @if ($interactive)
        <div class="mt-3 pt-2 border-t border-[var(--rule)] flex items-center justify-between text-xs">
            <span class="font-bold text-[var(--brand-text)]">
                @if ($page->price_tier)
                    {{ $page->price_tier->shortLabel() }} {{ $page->price_tier->label() }}
                @else
                    {{ $page->theme->label() }}
                @endif
            </span>
            <span class="text-[var(--ink-3)]">{{ $isService ? 'By appointment' : 'Direct WhatsApp ordering' }}</span>
        </div>
    @endif
</article>