@props(['question'])

<details class="card p-5 group bg-[var(--card)]">
    <summary class="flex items-center justify-between cursor-pointer list-none text-sm font-bold text-[var(--ink)]">
        <span>{{ $question }}</span>
        <span class="text-xs text-[var(--ink-3)] group-open:rotate-180 transition-transform">▼</span>
    </summary>
    <div class="text-xs text-[var(--ink-2)] mt-3 pt-3 border-t border-[var(--rule)] leading-relaxed">
        {{ $slot }}
    </div>
</details>