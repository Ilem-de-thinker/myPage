@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $image = $page->cover_path ?: $block->field('image');
    $headline = $block->field('headline') ?: 'Time set aside for your best self';
    $subtext = $block->field('subtext');
    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I would like to ask about an appointment.')
        : null;
@endphp

<section class="premium-hero premium-hero-editorial overflow-hidden border-b border-[var(--rule)]">
    <div class="shell grid min-h-[calc(100dvh-4rem)] items-stretch gap-8 py-6 md:grid-cols-12 md:gap-12 md:py-10">
        <div class="flex flex-col justify-between py-8 md:col-span-5 md:py-14">
            <div>
                <p class="t-meta mb-8 text-[var(--brand-text)]">{{ $page->locationLabel() ?: 'Private appointments' }}</p>
                <h1 class="premium-hero-title max-w-xl text-[var(--ink)]">{{ $headline }}</h1>
                @if ($subtext)
                    <p class="t-body-lg mt-7 max-w-lg text-[var(--ink-2)]">{{ $subtext }}</p>
                @endif
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', ['slug' => $page->slug]) }}"
                       class="inline-flex min-h-12 items-center justify-center bg-[var(--brand)] px-6 text-sm font-bold text-[var(--brand-ink)]">
                        Reserve a time
                    </a>
                    @if ($whatsapp)
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex min-h-12 items-center justify-center border border-[var(--rule-strong)] px-6 text-sm font-semibold text-[var(--ink)] hover:bg-[var(--brand-wash)]">
                            Speak with the studio
                        </a>
                    @endif
                </div>
            </div>
            <p class="mt-14 max-w-xs border-t border-[var(--rule)] pt-5 text-xs leading-relaxed text-[var(--ink-2)]">Thoughtful service, clear pricing, and an appointment that starts on time.</p>
        </div>

        <div class="relative min-h-[22rem] md:col-span-7 md:min-h-0">
            @if ($image)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt="" width="1200" height="1500" fetchpriority="high" decoding="async" class="h-full min-h-[22rem] w-full object-cover md:min-h-0">
            @else
                <div class="flex h-full min-h-[22rem] items-end bg-[var(--brand-wash)] p-6 md:p-10">
                    <span class="max-w-xs font-[family-name:var(--font-heading)] text-3xl leading-tight text-[var(--brand-text)]">A calm room for work that matters.</span>
                </div>
            @endif
            <div class="absolute bottom-4 left-4 bg-[var(--paper)] px-4 py-3 text-xs text-[var(--ink)] shadow-[var(--shadow-lift)] md:bottom-6 md:left-6">
                <span class="block font-semibold">{{ $page->name }}</span>
                <span class="mt-1 block text-[var(--ink-3)]">{{ $page->locationLabel() ?: 'By appointment' }}</span>
            </div>
        </div>
    </div>
</section>
