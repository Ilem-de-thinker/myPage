@props([
    'href',
    'label',
    'icon',
    'active' => false,
])

<a href="{{ $href }}"
   @class([
       'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium transition-colors',
       'bg-brand-wash font-semibold text-brand-text' => $active,
       'text-ink-2 hover:bg-tint hover:text-ink' => ! $active,
   ])
   @if($active) aria-current="page" @endif>
    <x-ui.icon :name="$icon" class="size-4 shrink-0 {{ $active ? 'text-brand-text' : 'text-ink-3 group-hover:text-ink-2' }}" />
    <span>{{ $label }}</span>
</a>
