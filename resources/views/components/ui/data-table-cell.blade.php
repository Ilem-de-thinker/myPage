@php
    /** @var array $col */
    /** @var mixed $value */
    $type = $col['type'] ?? 'text';
@endphp
@if ($type === 'image')
    @if ($value)
        <img src="{{ $value }}" class="w-12 h-12 rounded-lg object-cover">
    @else
        <div class="w-12 h-12 rounded-lg bg-slate-100"></div>
    @endif
@elseif ($type === 'badge')
    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $value === 'active' ? 'bg-emerald-100 text-emerald-700' : ($value === 'archived' ? 'bg-slate-100 text-slate-500' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst((string) $value) }}</span>
@else
    {{ $value }}
@endif