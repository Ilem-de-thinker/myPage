@props([
    // 'store' or 'service'. The only thing that changes is the badge treatment
    // and its icon; the card itself is identical.
    'variant',
    'title',
    'body',
    'demoHref',
    'demoLabel',
    'features' => [],
])

<article class="card p-6 sm:p-8 bg-[var(--card)]">
    <div class="flex items-center justify-between mb-4">
        <span @class([
            'inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-[var(--radius-sm)] border',
            'text-[var(--brand-text)] bg-[var(--brand-wash)] border-[var(--brand-edge)]' => $variant === 'store',
            'text-amber-600 bg-amber-500/10 border-amber-500/20' => $variant === 'service',
        ])>
            @if ($variant === 'store')
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            @else
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            @endif
            {{ $variant === 'store' ? 'Storefront Theme' : 'Service Booking Theme' }}
        </span>
        <a href="{{ $demoHref }}" class="text-xs font-bold text-[var(--brand-text)] hover:underline">
            {{ $demoLabel }} →
        </a>
    </div>

    <h3 class="t-title text-[var(--ink)] mb-2">{{ $title }}</h3>
    <p class="t-body text-xs text-[var(--ink-2)] mb-6 leading-relaxed">{{ $body }}</p>

    {{-- The mini live preview. Slot rather than markup: the two previews show
         different things, and only the card around them is shared. --}}
    {{ $preview }}

    <ul class="mt-6 space-y-2 text-xs text-[var(--ink-2)]">
        @foreach ($features as $feature)
            <x-platform.check-list>{{ $feature }}</x-platform.check-list>
        @endforeach
    </ul>
</article>