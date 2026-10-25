@props(['variant' => 'primary', 'type' => 'button'])
@php($variants = ['primary' => 'bg-brand text-brand-ink hover:bg-brand-hover', 'secondary' => 'border border-rule-strong bg-card text-ink hover:bg-surface', 'ghost' => 'text-ink-2 hover:bg-surface hover:text-ink', 'danger' => 'bg-danger text-white hover:opacity-90'])
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50 '.$variants[$variant]]) }}>{{ $slot }}</button>
