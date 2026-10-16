@props([
    'page',
    // The marquee and the mobile grid render an inert copy of every card for the
    // loop. An inert copy is not a second tab stop pointing at the same shop.
    'interactive' => true,
])

@php
    use App\Domains\Seller\Enums\Theme;
    use App\Shared\Support\StoreUrl;

    $isService = $page->theme === Theme::Service;
    $url = StoreUrl::home($page);
    $where = $page->locationLabel();
    $origin = $page->city ?: $page->state;
    $summary = $page->bio ?: $page->tagline;
    $noun = $isService ? 'bookings' : 'orders';
@endphp

<article class="card p-5 group">
    <div class="flex items-start gap-4">
        <div class="w-16 h-16 rounded-[var(--radius)] overflow-hidden shrink-0 border border-[var(--rule)] bg-[var(--surface)]">
            @if ($page->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}"
                     alt="Logo for {{ $page->name }}"
                     width="64"
                     height="64"
                     loading="lazy"
                     class="w-full h-full object-cover">
            @else
                {{-- Never an empty box: a seller with no logo still gets a mark
                     carrying their name. --}}
                <span class="w-full h-full flex items-center justify-center font-[family-name:var(--font-heading)] text-xl font-extrabold text-[var(--brand-text)]"
                      aria-hidden="true">{{ mb_strtoupper(mb_substr($page->name, 0, 1)) }}</span>
            @endif
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <span @class([
                    'inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-[var(--radius-sm)] border',
                    'text-[var(--brand-text)] bg-[var(--brand-wash)] border-[var(--brand-edge)]' => ! $isService,
                    'text-amber-700 dark:text-amber-300 bg-amber-500/10 border-amber-500/20' => $isService,
                ])>
                    @if ($isService)
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/></svg>
                        Service Studio
                    @else
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Verified Store
                    @endif
                </span>
                @if ($page->price_tier)
                    <span class="text-xs font-medium text-[var(--ink-3)]">{{ $page->price_tier->shortLabel() }} · {{ $page->price_tier->label() }}</span>
                @endif
            </div>

            <h3 class="text-base font-bold text-[var(--ink)] mt-1.5 truncate">
                @if ($interactive)
                    <a href="{{ $url }}" class="card-stretched-link hover:underline">{{ $page->name }}</a>
                @else
                    {{ $page->name }}
                @endif
            </h3>

            <p class="t-meta mt-0.5">
                {{ $where ? $where.' · ' : '' }}{{ $page->hasRating() ? $page->ratingLabel().' ★ ('.$page->review_count.' '.$noun.')' : $page->theme->label() }}
            </p>
        </div>
    </div>

    @if ($summary)
        <p class="text-xs text-[var(--ink-2)] mt-4 line-clamp-2 leading-relaxed">{{ $summary }}</p>
    @endif

    <div class="mt-4 pt-3 border-t border-[var(--rule)] flex items-center justify-between text-xs">
        <span class="text-[var(--ink-3)]">
            @if ($isService)
                Appointments in {{ $origin ?: 'Nigeria' }}
            @else
                Dispatched from {{ $origin ?: 'Nigeria' }}
            @endif
        </span>
        <span class="font-semibold text-[var(--brand-text)] group-hover:underline">{{ $isService ? 'Check Calendar' : 'Visit Storefront' }} →</span>
    </div>
</article>