@props([
    'name',
    'size' => 'size-4',
    'stroke' => '1.8',
])

@php($paths = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    'storefront' => '<path d="M3 10h18"/><path d="M5 10v10h14V10"/><path d="M4 10 6 4h12l2 6"/><path d="M9 20v-5h6v5"/>',
    'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'shopping-bag' => '<path d="M6 8h12l1 13H5L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    'tag' => '<path d="M20 13 13 20 4 11V4h7l9 9Z"/><circle cx="8" cy="8" r="1"/>',
    'receipt' => '<path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
    'cart' => '<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M3 4h2l2.5 11h10L20 8H6"/>',
    'truck' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/>',
    'map-pin' => '<path d="M12 21s7-5.4 7-11a7 7 0 0 0-14 0c0 5.6 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
    'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
    'message' => '<path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.5 8.5 0 0 1-4-1L4 20l1.5-3.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
    'chart' => '<path d="M4 19V5M4 19h17"/><path d="m7 15 3-4 3 2 5-6"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
    'credit-card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
    'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 5l3 3M14 7l3 3"/>',
    'life-buoy' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="m5.6 5.6 4.3 4.3M14.1 14.1l4.3 4.3M18.4 5.6l-4.3 4.3M9.9 14.1l-4.3 4.3"/>',
    'badge' => '<path d="m12 3 2 2 3-.2.8 2.8 2.5 1.6-1.4 2.6.6 3-2.8.8-1.7 2.5-3-1.1-3 1.1-1.7-2.5-2.8-.8.6-3-1.4-2.6L5 7.6 5.8 4.8l3 .2 2-2Z"/><path d="m9 12 2 2 4-4"/>',
    'settings' => '<path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="4"/>',
    'shield' => '<path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
    'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/>',
    'flag' => '<path d="M5 21V4"/><path d="M5 5c4-3 7 3 14 0v9c-7 3-10-3-14 0"/>',
    'spark' => '<path d="m12 3 1.3 5.7L19 10l-5.7 1.3L12 17l-1.3-5.7L5 10l5.7-1.3L12 3ZM19 16l.6 2.4L22 19l-2.4.6L19 22l-.6-2.4L16 19l2.4-.6L19 16Z"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
])

<svg {{ $attributes->merge(['class' => $size]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths[$name] ?? $paths['spark'] !!}
</svg>
