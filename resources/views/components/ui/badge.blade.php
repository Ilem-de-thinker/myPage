@props(['variant' => 'neutral'])
@php($variants = ['neutral' => 'border-rule bg-surface text-ink-2', 'success' => 'border-ok-edge bg-ok-wash text-ok-text', 'warning' => 'border-warn-edge bg-warn-wash text-warn-text', 'danger' => 'border-danger-edge bg-danger-wash text-danger-text', 'brand' => 'border-brand-edge bg-brand-wash text-brand-text'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold '.$variants[$variant]]) }}>{{ $slot }}</span>
