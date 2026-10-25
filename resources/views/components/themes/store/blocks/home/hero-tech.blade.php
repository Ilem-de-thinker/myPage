@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $image = $page->heroImage();
    $headline = $block->field('headline') ?: 'Reliable supply for serious work';
    $whatsapp = filled($page->whatsapp_number) ? WhatsApp::link($page, 'Hello '.$page->name.', I need help choosing a product.') : null;
@endphp

<section class="premium-hero premium-hero-tech border-b border-[var(--rule)]">
    <div class="shell grid min-h-[calc(100dvh-4rem)] items-center gap-10 py-12 md:grid-cols-12 md:gap-0">
        <div class="md:col-span-7 md:pr-16">
            <p class="t-meta mb-6 text-[var(--brand-text)]">{{ $page->locationLabel() ?: 'Business supply, clearly handled' }}</p>
            <h1 class="premium-hero-title max-w-3xl text-[var(--ink)]">{{ $headline }}</h1>
            @if ($block->field('subtext'))<p class="t-body-lg mt-7 max-w-xl text-[var(--ink-2)]">{{ $block->field('subtext') }}</p>@endif
            <div class="mt-9 flex flex-wrap gap-3"><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}" class="inline-flex min-h-12 items-center bg-[var(--brand)] px-6 text-sm font-bold text-[var(--brand-ink)]">{{ $block->field('button_label') ?: 'View products' }}</a>@if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center border border-[var(--rule-strong)] px-5 text-sm font-semibold text-[var(--ink)]">Talk to sales</a>@endif</div>
        </div>
        <div class="grid gap-px border-y border-[var(--rule)] bg-[var(--rule)] md:col-span-5 md:border-l md:border-y-0">
            <div class="bg-[var(--paper)] p-6"><span class="t-meta">01 / Catalogue</span><p class="mt-10 font-[family-name:var(--font-heading)] text-2xl font-bold text-[var(--ink)]">{{ $page->name }}</p><p class="mt-2 text-xs leading-5 text-[var(--ink-2)]">Browse current stock and request the right option without guesswork.</p></div>
            <div class="bg-[var(--paper)] p-6"><span class="t-meta">02 / Response</span><p class="mt-10 font-[family-name:var(--font-heading)] text-2xl font-bold text-[var(--ink)]">Human support</p><p class="mt-2 text-xs leading-5 text-[var(--ink-2)]">WhatsApp conversion when a product needs a little context.</p></div>
            @if ($image)<img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt="" width="1000" height="700" loading="lazy" decoding="async" class="h-48 w-full object-cover">@endif
        </div>
    </div>
</section>
