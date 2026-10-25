@props([
    'ghost' => 'FIND IT. GO THERE.',
    'route' => true,
])

{{-- Background texture sits behind the page content. --}}
<div class="platform-background-system" aria-hidden="true">
    <x-platform.bg.grid />
    <x-platform.bg.ghost-typography :text="$ghost" speed="0.018" class="platform-ghost-type platform-ghost-type-primary" />
    @if ($route)
        <x-platform.bg.route-line class="platform-route-line-one" />
    @endif
</div>

{{-- Grain and cursor light sit above the content so opaque tone bands still
     carry the editorial texture. Both are pointer-events: none. --}}
<div class="platform-overlay-system" aria-hidden="true">
    <x-platform.bg.grain />
    <x-platform.bg.spotlight />
</div>
