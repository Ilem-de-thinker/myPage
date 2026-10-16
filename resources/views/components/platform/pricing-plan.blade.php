@props([
    'name',
    'tagline',
    'price',
    'interval' => '/ month',
    'description',
    // Each row is ['label' => …, 'code' => …, 'highlight' => bool, 'muted' => bool].
    'features' => [],
    'ctaLabel',
    'ctaHref',
    // The featured card is the one with the brand border and the badge.
    'featured' => false,
    'badge' => null,
])

<div @class([
    'card p-8 flex flex-col justify-between relative',
    'bg-[var(--card)]',
    'border-2 border-[var(--brand)] shadow-[var(--shadow-lift)]' => $featured,
])>
    @if ($featured && $badge)
        <div class="absolute -top-3 left-1/2 -translate-x-1/2 z-10 px-3 py-0.5 rounded-[var(--radius-pill)] bg-[var(--brand)] text-[var(--brand-ink)] text-[11px] font-bold tracking-tight shadow-sm">
            {{ $badge }}
        </div>
    @endif

    <div>
        <div @class(['flex items-center justify-between mb-4', 'mt-2' => $featured])>
            <h3 class="text-base font-bold text-[var(--ink)]">{{ $name }}</h3>
            <span @class([
                'text-xs font-semibold',
                'text-[var(--brand-text)]' => $featured,
                'text-[var(--ink-3)]' => ! $featured,
            ])>{{ $tagline }}</span>
        </div>
        <p class="text-xs text-[var(--ink-2)] mb-6 leading-relaxed">{{ $description }}</p>
        <div class="mb-6 pb-6 border-b border-[var(--rule)]">
            <span class="t-price-lg text-[var(--ink)]">{{ $price }}</span>
            <span class="text-xs text-[var(--ink-3)]">{{ $interval }}</span>
        </div>

        <ul class="space-y-3 text-xs text-[var(--ink-2)]">
            @foreach ($features as $feature)
                <x-platform.check-list :muted="$feature['muted'] ?? false" :shrink="true">
                    <span @class(['font-bold text-[var(--ink)]' => $feature['highlight'] ?? false])>{{ $feature['label'] }}</span>
                    @isset ($feature['code'])
                        (<code class="text-[11px] font-mono">{{ $feature['code'] }}</code>)
                    @endisset
                </x-platform.check-list>
            @endforeach
        </ul>
    </div>

    <div class="pt-8">
        <a href="{{ $ctaHref }}"
           @class([
               'w-full h-11 rounded-[var(--radius)] font-bold text-xs flex items-center justify-center',
               'bg-[var(--brand)] text-[var(--brand-ink)] hover:opacity-90 transition-opacity' => $featured,
               'bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--paper)] transition-colors' => ! $featured,
           ])>{{ $ctaLabel }}</a>
    </div>
</div>