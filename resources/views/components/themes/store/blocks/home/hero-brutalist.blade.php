@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $image = $page->heroImage();
    $headline = $block->field('headline') ?: 'Made in public. Bought with confidence.';
    $subtext = $block->field('subtext');
    $button = $block->field('button_label') ?: 'See what is new';
    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I want to place an order.')
        : null;
@endphp

<section class="premium-hero premium-hero-brutalist overflow-hidden border-b-2 border-[var(--ink)]">
    <div class="shell py-5 md:py-8">
        <div class="grid gap-0 md:grid-cols-12">
            <div class="flex min-h-[30rem] flex-col justify-between bg-[var(--brand)] p-6 text-[var(--brand-ink)] md:col-span-7 md:min-h-[calc(100dvh-8rem)] md:p-12 lg:p-16">
                <div class="flex items-center justify-between gap-4 text-xs font-bold">
                    <span>{{ $page->name }}</span>
                    <span>{{ $page->locationLabel() ?: 'Lagos · Nigeria' }}</span>
                </div>
                <div>
                    <h1 class="premium-hero-title max-w-3xl text-[var(--brand-ink)]">{{ $headline }}</h1>
                    @if ($subtext)
                        <p class="t-body-lg mt-7 max-w-xl text-[var(--brand-ink)]/80">{{ $subtext }}</p>
                    @endif
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
                           class="inline-flex min-h-12 items-center justify-center bg-[var(--paper)] px-6 text-sm font-bold text-[var(--ink)]">
                            {{ $button }}
                        </a>
                        @if ($whatsapp)
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex min-h-12 items-center justify-center border-2 border-[var(--brand-ink)] px-6 text-sm font-bold text-[var(--brand-ink)]">
                                Order direct
                            </a>
                        @endif
                    </div>
                </div>
                <p class="text-xs font-semibold text-[var(--brand-ink)]/75">Small batch · Direct conversation · No middlemen</p>
            </div>

            <div class="relative min-h-[18rem] border-2 border-t-0 border-[var(--ink)] bg-[var(--surface)] md:col-span-5 md:min-h-0 md:border-l-0 md:border-t-2">
                @if ($image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
                         alt=""
                         width="1000"
                         height="1200"
                         fetchpriority="high"
                         decoding="async"
                         class="h-full min-h-[18rem] w-full object-cover md:min-h-0">
                @else
                    <div class="flex h-full min-h-[18rem] items-end p-6 md:p-8">
                        <span class="text-3xl font-black leading-[0.95] text-[var(--ink)]">Your best work deserves a sharper first impression.</span>
                    </div>
                @endif
                <span class="absolute right-4 top-4 border-2 border-[var(--ink)] bg-[var(--paper)] px-3 py-2 text-xs font-bold text-[var(--ink)]">01 / Featured</span>
            </div>
        </div>
    </div>
</section>
