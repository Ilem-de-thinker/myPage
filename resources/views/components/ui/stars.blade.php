@props([
    'rating' => null,
    'count' => null,
    'size' => 'sm',
    'showCount' => true,
])

@php
    /**
     * The one place a star rating is drawn.
     *
     * Read-only. The input that writes one lives in <x-ui.review-form>; having
     * two components that both render stars is how a card ends up at 4.5 stars
     * and a form at 5, and nobody notices until a customer does.
     *
     * Two rules this enforces:
     *
     *  - The count is never omitted when there is a score. "4.5" alone reads as a
     *    consensus; one five-star review presented that way is a lie.
     *  - Zero reviews renders nothing at all, not "0.0". An unrated thing has no
     *    rating, and "0.0 out of 5" is a claim about it.
     */
    $hasRating = is_numeric($rating) && (int) $count > 0 && (float) $rating > 0;
    $rounded = $hasRating ? round((float) $rating * 2) / 2 : 0.0;
    $px = ['xs' => 12, 'sm' => 14, 'md' => 18][$size] ?? 14;
@endphp

@if ($hasRating)
    <span class="inline-flex items-center gap-1.5 align-middle"
          role="img"
          aria-label="{{ number_format($rounded, 1) }} out of 5{{ $showCount && $count ? ' stars, from '.$count.' '.\Illuminate\Support\Str::plural('review', $count) : '' }}">

        {{-- Two overlaid rows rather than five conditional stars: a half star is
             not a whole number of icons, and rounding it to a full one is a
             different lie. The empty row underneath is what makes the fill read
             as a proportion. --}}
        <span class="relative inline-block align-middle" style="line-height: 0">
            <span class="flex gap-0.5 text-slate-300 dark:text-slate-700" aria-hidden="true">
                @for ($i = 0; $i < 5; $i++)
                    <svg width="{{ $px }}" height="{{ $px }}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>
                @endfor
            </span>

            <span class="absolute inset-0 flex gap-0.5 overflow-hidden text-amber-400"
                  style="width: {{ ($rounded / 5) * 100 }}%" aria-hidden="true">
                @for ($i = 0; $i < 5; $i++)
                    <svg width="{{ $px }}" height="{{ $px }}" viewBox="0 0 24 24" fill="currentColor" class="shrink-0"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/></svg>
                @endfor
            </span>
        </span>

        @if ($showCount && $count)
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                {{ number_format($rounded, 1) }} <span class="text-slate-400 dark:text-slate-500">({{ $count }})</span>
            </span>
        @endif
    </span>
@elseif ($showCount && $count === 0)
    {{-- Brand new, nothing to say. "No reviews yet" invites the first one; an
         empty star row looks broken. --}}
    <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500">No reviews yet</span>
@endif