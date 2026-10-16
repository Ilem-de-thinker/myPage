@props(['page'])

@php
    use App\Domains\Admin\Models\BadgeApplication;
    use App\Domains\Admin\Models\BadgeStatus;

    /**
     * The badge is looked up per page render rather than cached, because it changes
     * rarely and a stale tick on someone's storefront is worse than one extra query.
     */
    $isVerified = BadgeApplication::where('store_page_id', $page->getKey())
        ->where('status', BadgeStatus::Approved->value)
        ->exists();

@endphp

@if ($isVerified)
  {{--
      The one piece of trust we can offer a stranger, so it sits beside the
      business's own name rather than somewhere in a footer. Painted from the
      tokens rather than fixed greens: on a dark surface a fixed emerald tint is
      a pale green slab.
  --}}
  <span
    class="inline-flex items-center gap-1 rounded-[var(--radius-pill)] px-2.5 py-1 text-[11px] font-semibold"
    style="background: var(--brand-wash); color: var(--brand-text); border: 1px solid var(--brand-edge)"
    title="This business has been verified by mypage.ng"
  >
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
    </svg>
    Verified
  </span>
@endif