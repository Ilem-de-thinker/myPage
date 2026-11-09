@extends('onboarding.layout')

@section('content')
    @php
        $ai = $data['ai_result'] ?? null;
        $categories = collect((array) ($ai['categories'] ?? []))
            ->filter(fn ($category): bool => is_string($category) && filled($category))
            ->values();
        $listings = (array) ($ai['listings'] ?? []);
    @endphp

    <div class="space-y-6">
        {{-- Header --}}
        <div class="space-y-2">
            <span class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-brand">
                <span class="size-1.5 rounded-full bg-brand"></span>
                Step 4 of 4 · Final review
            </span>
            <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink">Go Live</h1>
            <p class="text-xs sm:text-sm text-ink-2 max-w-md">Confirm your details and we will create your {{ $theme->siteNoun() }} as a draft. Nothing is published until you say so.</p>
        </div>

        {{-- Your business --}}
        <section class="overflow-hidden rounded-2xl border border-rule bg-card shadow-sm">
            <div class="border-b border-rule px-5 py-3">
                <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink-3">Your business</h2>
            </div>
            <dl class="grid grid-cols-1 gap-px bg-rule sm:grid-cols-2">
                <div class="bg-card p-4">
                    <dt class="text-[11px] font-medium text-ink-3">Business</dt>
                    <dd class="mt-0.5 truncate text-sm font-semibold text-ink">{{ $data['name'] ?? '—' }}</dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-[11px] font-medium text-ink-3">Website address</dt>
                    <dd class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-ink">
                        <svg class="size-3.5 shrink-0 text-ink-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18"/></svg>
                        <span class="truncate">mypage.ng/{{ $data['slug'] ?? '—' }}</span>
                    </dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-[11px] font-medium text-ink-3">Template</dt>
                    <dd class="mt-1"><span class="inline-flex items-center rounded-full border border-rule bg-surface px-2.5 py-0.5 text-[11px] font-semibold text-ink">{{ $theme->label() }}</span></dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-[11px] font-medium text-ink-3">WhatsApp</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink">{{ $data['whatsapp'] ?? 'Add later' }}</dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-[11px] font-medium text-ink-3">Location</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink">{{ trim(($data['city'] ?? '').', '.($data['state'] ?? ''), ', ') ?: '—' }}</dd>
                </div>
                <div class="bg-card p-4">
                    <dt class="text-[11px] font-medium text-ink-3">Brand colour</dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <span class="inline-block size-6 rounded-md border border-rule" style="background: {{ $data['brand_color'] ?? '#16a34a' }}"></span>
                        <span class="text-xs font-mono font-semibold uppercase text-ink-2">{{ $data['brand_color'] ?? '#16a34a' }}</span>
                    </dd>
                </div>
                <div class="bg-card p-4 sm:col-span-2">
                    <dt class="text-[11px] font-medium text-ink-3">Mercho setup</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink">{{ ($data['ai_enabled'] ?? true) ? 'Enabled' : 'Disabled' }}{{ ! empty($data['ai_goal']) ? ' · '.str_replace('_', ' ', $data['ai_goal']) : '' }}</dd>
                </div>
            </dl>
        </section>

        {{-- Mercho review --}}
        @if (! empty($ai))
            <section class="overflow-hidden rounded-2xl border border-rule bg-card shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-rule px-5 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-7 items-center justify-center rounded-lg bg-brand-wash text-brand">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/></svg>
                        </span>
                        <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink-3">Mercho review</h2>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-tint px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-2">AI draft</span>
                </div>

                <div class="space-y-4 px-5 py-4">
                    @if (filled($ai['tagline'] ?? null))
                        <div class="flex items-start gap-3">
                            <span class="mt-1 w-0.5 self-stretch rounded-full bg-brand"></span>
                            <div>
                                <p class="text-sm font-semibold leading-snug text-ink">“{{ $ai['tagline'] }}”</p>
                                <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.14em] text-ink-3">{{ $ai['business_type'] ?? 'store' }} direction</p>
                            </div>
                        </div>
                    @endif

                    @if (filled($ai['about'] ?? null))
                        <p class="whitespace-pre-line rounded-xl bg-surface p-4 text-xs leading-5 text-ink-2">{{ $ai['about'] }}</p>
                    @endif

                    @if (filled($ai['seo']['title'] ?? null))
                        <div class="rounded-xl border border-rule p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-ink-3">Search preview</p>
                            <p class="mt-2 truncate text-sm font-medium text-[#1558d6]">{{ $ai['seo']['title'] }}</p>
                            <p class="truncate text-[11px] text-ink-3">mypage.ng/{{ $data['slug'] ?? '' }}</p>
                            <p class="mt-1 line-clamp-2 text-[11px] leading-4 text-ink-2">{{ $ai['seo']['description'] ?? '' }}</p>
                        </div>
                    @endif

                    @if (filled($ai['review_flags'] ?? null))
                        <div class="rounded-xl border border-warn-edge bg-warn-wash p-4">
                            <p class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-warn-text">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
                                Worth confirming before publish
                            </p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-warn-text">
                                @foreach ($ai['review_flags'] as $flag)
                                    <li>{{ $flag }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <a href="{{ route('onboarding.show', 'brand') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-3 hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>

        <form method="POST" action="{{ route('onboarding.store', 'review') }}" class="space-y-4">
            @csrf

            {{-- Proposed categories --}}
            @if ($categories->isNotEmpty())
                <fieldset class="rounded-2xl border border-rule bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <legend class="text-sm font-bold text-ink">Proposed categories</legend>
                        <span class="rounded-full bg-tint px-2.5 py-0.5 text-[10px] font-bold text-ink-2">{{ $categories->count() }} proposed</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-3">Tick the ones to keep — they are created on your {{ $theme->siteNoun() }} so products can be filed straight away.</p>
                    <input type="hidden" name="starter_categories[]" value="">
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($categories as $category)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-rule p-3 transition-colors hover:border-rule-strong has-[:checked]:border-brand has-[:checked]:bg-brand-wash/40">
                                <input type="checkbox" name="starter_categories[]" value="{{ $category }}" checked class="size-4 shrink-0 accent-brand">
                                <span class="text-xs font-semibold text-ink">{{ $category }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            {{-- Starter drafts --}}
            @if ($listings !== [])
                <fieldset class="rounded-2xl border border-rule bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <legend class="text-sm font-bold text-ink">Starter drafts</legend>
                        <span class="rounded-full bg-tint px-2.5 py-0.5 text-[10px] font-bold text-ink-2">{{ count($listings) }} proposed</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-3">Keep or remove each proposed item. They remain drafts after confirmation.</p>
                    <div class="mt-3 grid gap-2">
                        @foreach ($listings as $index => $listing)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-3 transition-colors hover:border-rule-strong has-[:checked]:border-brand has-[:checked]:bg-brand-wash/40">
                                <input type="checkbox" name="starter_keep[]" value="{{ $index }}" checked class="mt-1 size-4 shrink-0 accent-brand">
                                <span class="text-xs"><strong class="text-ink">{{ $listing['name'] ?? 'Untitled' }}</strong><span class="mt-1 block leading-5 text-ink-2">{{ $listing['description'] ?? '' }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <div class="space-y-2">
                <button type="submit" class="w-full rounded-xl bg-brand px-4 py-4 text-sm font-bold text-white shadow-sm transition-all hover:bg-brand-hover">Create my {{ $theme->siteNoun() }}</button>
                <p class="text-center text-[11px] text-ink-3">Your page starts as a <span class="font-semibold text-ink-2">draft</span> — publish it from your dashboard when you are ready.</p>
            </div>
        </form>
    </div>
@endsection
