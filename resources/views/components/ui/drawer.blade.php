@props(['title' => null, 'side' => 'right'])
<div x-data="{ open: false }" @keydown.escape.window="open = false" {{ $attributes }}>
    <button type="button" @click="open = true">{{ $trigger ?? '' }}</button>
    <div x-cloak x-show="open" class="fixed inset-0 z-50 bg-ink/40" role="dialog" aria-modal="true"><div @click.outside="open = false" class="fixed inset-y-0 {{ $side === 'left' ? 'left-0' : 'right-0' }} w-[min(28rem,calc(100vw-1rem))] overflow-y-auto bg-card p-6 shadow-2xl"><div class="flex items-center justify-between gap-4">@if($title)<h2 class="text-lg font-bold text-ink">{{ $title }}</h2>@endif<button type="button" @click="open = false" class="min-h-11 min-w-11 rounded-xl text-ink-2">×</button></div><div class="mt-5">{{ $slot }}</div></div></div>
</div>
