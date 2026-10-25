@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $image = $page->heroImage();
    $headline = $block->field('headline') ?: 'The clear choice for what comes next';
    $subtext = $block->field('subtext');
    $button = $block->field('button_label') ?: 'Browse catalogue';
    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I would like to learn more.')
        : null;
@endphp

<section class="premium-hero premium-hero-structured border-b border-[var(--rule)]">
    <div class="shell grid min-h-[calc(100dvh-4rem)] items-center gap-10 py-16 md:grid-cols-12 md:gap-8 lg:py-24">
        <div class="md:col-span-1 md:border-r md:border-[var(--rule)] md:py-3">
            <span class="hidden [writing-mode:vertical-rl] text-xs font-semibold tracking-[0.16em] text-[var(--ink-3)] md:inline">{{ $page->name }}</span>
        </div>

        <div class="md:col-span-6 md:pl-8">
            <p class="t-meta mb-6 text-[var(--brand-text)]">{{ $page->locationLabel() ?: 'Independent business' }}</p>
            <h1 class="premium-hero-title max-w-2xl text-[var(--ink)]">{{ $headline }}</h1>
            @if ($subtext)
                <p class="t-body-lg mt-7 max-w-xl text-[var(--ink-2)]">{{ $subtext }}</p>
            @endif
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
                   class="inline-flex min-h-12 items-center justify-center bg-[var(--brand)] px-6 text-sm font-bold text-[var(--brand-ink)]">
                    {{ $button }}
                </a>
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex min-h-12 items-center justify-center border border-[var(--rule-strong)] px-6 text-sm font-semibold text-[var(--ink)] hover:bg-[var(--brand-wash)]">
                        Ask a question
                    </a>
                @endif
            </div>
        </div>

        <div class="relative md:col-span-5">
            <div class="absolute -inset-4 -z-10 border border-[var(--brand-edge)] md:-inset-7"></div>
            @if ($image)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
                     alt=""
                     width="1000"
                     height="1100"
                     fetchpriority="high"
                     decoding="async"
                     class="aspect-[5/6] w-full object-cover">
            @else
                <div class="flex aspect-[5/6] items-end bg-[var(--surface)] p-7">
                    <span class="max-w-xs text-2xl font-bold leading-tight text-[var(--ink)]">{{ $page->tagline ?: 'Good work, clearly presented.' }}</span>
                </div>
            @endif
        </div>
    </div>
</section>
