@php
    $items = collect($block->field('items') ?? [])
        ->filter(fn ($item): bool => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))
        ->values();
@endphp

@if ($items->isNotEmpty())
    <section class="section-y border-t border-[var(--rule)]" aria-labelledby="service-faq-heading">
        <div class="shell grid gap-8 lg:grid-cols-12 lg:gap-12">
            <div class="lg:col-span-4">
                <p class="t-meta">Before your appointment</p>
                <h2 id="service-faq-heading" class="t-display-2 mt-2 text-[var(--ink)]">{{ $block->field('heading') ?: 'Before you book' }}</h2>
            </div>
            <div class="space-y-3 lg:col-span-8">
                @foreach ($items as $item)
                    <details class="group rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--card)] px-4 open:border-[var(--brand-edge)] sm:px-5">
                        <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-3 text-sm font-bold text-[var(--ink)] [&::-webkit-details-marker]:hidden">
                            <span>{{ $item['question'] }}</span>
                            <span class="text-xl font-normal text-[var(--brand-text)] transition-transform group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="max-w-2xl pb-4 text-sm leading-6 text-[var(--ink-2)]">{{ $item['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
