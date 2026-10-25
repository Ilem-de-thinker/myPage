@props(['page', 'block'])

@php
    use App\Domains\Booking\Models\Service;
    use App\Shared\Support\WhatsApp;

    $image = $page->cover_path ?: $block->field('image');
    $headline = $block->field('headline') ?: 'Clear scope. Good work. No guesswork.';
    $serviceCount = Service::withoutGlobalScopes()->where('store_page_id', $page->id)->where('is_active', true)->count();
    $whatsapp = filled($page->whatsapp_number) ? WhatsApp::link($page, 'Hello '.$page->name.', I would like to discuss a service.') : null;
@endphp

<section class="premium-hero premium-hero-tech border-b border-[var(--rule)]">
    <div class="shell grid min-h-[calc(100dvh-4rem)] items-center gap-10 py-12 md:grid-cols-12 md:gap-0"><div class="md:col-span-7 md:pr-16"><p class="t-meta mb-6 text-[var(--brand-text)]">{{ $page->locationLabel() ?: 'Professional services' }}</p><h1 class="premium-hero-title max-w-3xl text-[var(--ink)]">{{ $headline }}</h1>@if ($block->field('subtext'))<p class="t-body-lg mt-7 max-w-xl text-[var(--ink-2)]">{{ $block->field('subtext') }}</p>@endif<div class="mt-9 flex flex-wrap gap-3"><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', ['slug' => $page->slug]) }}" class="inline-flex min-h-12 items-center bg-[var(--brand)] px-6 text-sm font-bold text-[var(--brand-ink)]">{{ $block->field('button_label') ?: 'Book a consultation' }}</a>@if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center border border-[var(--rule-strong)] px-5 text-sm font-semibold text-[var(--ink)]">Talk to us</a>@endif</div></div><div class="grid gap-px border-y border-[var(--rule)] bg-[var(--rule)] md:col-span-5 md:border-l md:border-y-0"><div class="bg-[var(--paper)] p-6"><span class="t-meta">01 / Scope</span><p class="mt-10 font-[family-name:var(--font-heading)] text-2xl font-bold text-[var(--ink)]">{{ $serviceCount }} services</p><p class="mt-2 text-xs leading-5 text-[var(--ink-2)]">Choose the right package before you choose a time.</p></div><div class="bg-[var(--paper)] p-6"><span class="t-meta">02 / Availability</span><p class="mt-10 font-[family-name:var(--font-heading)] text-2xl font-bold text-[var(--ink)]">Structured booking</p><p class="mt-2 text-xs leading-5 text-[var(--ink-2)]">See real appointment slots and receive a clear confirmation.</p></div>@if ($image)<img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt="" width="1000" height="700" loading="lazy" decoding="async" class="h-48 w-full object-cover">@endif</div></div>
</section>
