@props(['number', 'title'])

<li class="card p-6 bg-[var(--surface)] border border-[var(--rule)] relative">
    <span class="text-3xl font-extrabold text-[var(--brand)] font-mono block mb-4">{{ $number }}</span>
    <h3 class="t-title text-[var(--ink)] mb-2">{{ $title }}</h3>
    <p class="t-body text-xs text-[var(--ink-2)] leading-relaxed">
        {{ $slot }}
    </p>
</li>