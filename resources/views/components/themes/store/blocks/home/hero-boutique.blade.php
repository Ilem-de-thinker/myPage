@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $image = $page->heroImage();
    $headline = $block->field('headline') ?: 'Made slowly, chosen well';
    $whatsapp = filled($page->whatsapp_number) ? WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your collection.') : null;
@endphp

<section class="premium-hero premium-hero-boutique overflow-hidden bg-[var(--brand-wash)]">
    <div class="shell grid min-h-[calc(100dvh-4rem)] items-center gap-8 py-8 md:grid-cols-12 md:gap-14">
        <div class="order-2 md:order-1 md:col-span-5">
            <p class="t-meta mb-6 text-[var(--brand-text)]">{{ $page->locationLabel() ?: 'Made in Nigeria' }}</p>
            <h1 class="premium-hero-title max-w-lg text-[var(--ink)]">{{ $headline }}</h1>
            @if ($block->field('subtext'))<p class="t-body-lg mt-6 max-w-md text-[var(--ink-2)]">{{ $block->field('subtext') }}</p>@endif
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}" class="inline-flex min-h-12 items-center rounded-full bg-[var(--brand)] px-6 text-sm font-bold text-[var(--brand-ink)]">{{ $block->field('button_label') ?: 'Browse the collection' }}</a>
                @if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center rounded-full border border-[var(--brand-edge)] px-5 text-sm font-semibold text-[var(--ink)]">Ask about a piece</a>@endif
            </div>
            <p class="mt-12 max-w-xs border-t border-[var(--brand-edge)] pt-4 text-xs leading-5 text-[var(--ink-2)]">Small-batch pieces, thoughtful details, and a human answer when you need one.</p>
        </div>
        <div class="relative order-1 md:order-2 md:col-span-7">
            @if ($image)<img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt="" width="1200" height="1400" fetchpriority="high" decoding="async" class="h-[min(70vh,42rem)] w-full rounded-[var(--radius-lg)] object-cover">@else<div class="flex h-[min(70vh,42rem)] items-end rounded-[var(--radius-lg)] bg-[var(--brand-wash-2)] p-7"><span class="max-w-xs font-[family-name:var(--font-heading)] text-3xl leading-tight text-[var(--brand-text)]">A warm welcome to the things worth keeping.</span></div>@endif
            <span class="absolute bottom-5 left-5 rounded-full bg-[var(--paper)] px-4 py-2 text-xs font-semibold text-[var(--ink)] shadow-[var(--shadow-lift)]">{{ $page->name }}</span>
        </div>
    </div>
</section>
