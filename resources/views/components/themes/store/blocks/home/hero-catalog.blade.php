@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $image = $page->heroImage();
    $headline = $block->field('headline') ?: 'Find your next favourite';
    $subtext = $block->field('subtext');
    $button = $block->field('button_label') ?: 'Shop all products';
    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I need help choosing an item.')
        : null;
@endphp

<section class="premium-hero premium-hero-catalog border-b border-[var(--rule)] bg-[var(--surface)]">
    <div class="shell grid gap-6 py-8 md:grid-cols-12 md:gap-8 md:py-14">
        <div class="md:col-span-7">
            <p class="t-meta mb-5 text-[var(--brand-text)]">{{ $page->locationLabel() ?: 'Shop independently' }}</p>
            <h1 class="premium-hero-title max-w-3xl text-[var(--ink)]">{{ $headline }}</h1>
            @if ($subtext)
                <p class="t-body-lg mt-5 max-w-xl text-[var(--ink-2)]">{{ $subtext }}</p>
            @endif
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
                   class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-sm)] bg-[var(--brand)] px-5 text-sm font-bold text-[var(--brand-ink)]">
                    {{ $button }}
                </a>
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-sm)] border border-[var(--rule-strong)] bg-[var(--paper)] px-5 text-sm font-semibold text-[var(--ink)]">
                        Need help choosing?
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 md:col-span-5 md:grid-cols-1 md:grid-rows-2">
            @if ($image)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
                     alt=""
                     width="900"
                     height="600"
                     fetchpriority="high"
                     decoding="async"
                     class="col-span-2 aspect-[16/8] w-full object-cover md:col-span-1 md:row-span-2 md:aspect-auto">
            @else
                <div class="col-span-2 flex min-h-52 items-end bg-[var(--brand-wash)] p-5 md:col-span-1 md:row-span-2">
                    <span class="max-w-xs text-2xl font-bold leading-tight text-[var(--brand-text)]">{{ $page->tagline ?: 'Browse well-made pieces from an independent Nigerian business.' }}</span>
                </div>
            @endif
        </div>
    </div>
</section>
