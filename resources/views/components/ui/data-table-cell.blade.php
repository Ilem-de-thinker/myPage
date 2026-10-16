@php
    /** @var array $col */
    /** @var mixed $value */
    $type = $col['type'] ?? 'text';
@endphp
@if ($type === 'image')
    @if ($value)
        <img src="{{ $value }}" class="w-12 h-12 rounded-lg object-cover">
    @else
        <div class="w-12 h-12 rounded-lg bg-tint"></div>
    @endif
@elseif ($type === 'badge')
    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $value === 'active' ? 'bg-ok-wash text-ok-text' : ($value === 'archived' ? 'bg-tint text-ink-3' : 'bg-warn-wash text-warn-text') }}">{{ ucfirst((string) $value) }}</span>
@else
    {{ $value }}
@endif