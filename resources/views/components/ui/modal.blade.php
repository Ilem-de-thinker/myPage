@props(['name', 'title' => null])
<div x-data="{ open: false }" @keydown.escape.window="open = false" {{ $attributes }}>
    <button type="button" @click="open = true">{{ $trigger ?? '' }}</button>
    <div x-cloak x-show="open" class="fixed inset-0 z-50 grid place-items-center bg-ink/50 p-4" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div @click.outside="open = false" class="w-full max-w-lg rounded-2xl border border-rule bg-card p-6 shadow-2xl"><div class="flex items-center justify-between gap-4">@if($title)<h2 class="text-lg font-bold text-ink">{{ $title }}</h2>@endif<button type="button" @click="open = false" class="min-h-11 min-w-11 rounded-xl text-ink-2">×</button></div><div class="mt-4">{{ $slot }}</div></div>
    </div>
</div>
