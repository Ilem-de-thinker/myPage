@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'backLabel' => 'Back',
    'width' => null,
])

<div @class(['mb-6', $width])>
    @if ($back)
        <a href="{{ $back }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-ink mb-2">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            {{ $backLabel }}
        </a>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-extrabold text-ink tracking-tight">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-xs text-body mt-1">{{ $subtitle }}</p>
            @endif
        </div>

        @if (trim($slot))
            <div class="flex items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>