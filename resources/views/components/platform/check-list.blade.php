@props([
    // Rows that are part of the deal carry a tick; rows that are simply not
    // included are muted and carry nothing, which is the only difference
    // between the two.
    'muted' => false,
    // Inside a dense grid the icon must not be squeezed by a long label.
    'shrink' => false,
])

<li @class([
    'flex items-center gap-2',
    'text-[var(--ink-3)]' => $muted,
])>
    @unless ($muted)
        <svg @class(['w-4 h-4 text-[var(--brand)]', 'shrink-0' => $shrink]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    @endunless
    {{ $slot }}
</li>