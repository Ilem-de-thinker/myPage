@props(['page'])

@php
    /**
     * verified_at is the canonical trust signal: the admin approval action is the
     * only thing that stamps it, and the directory cards, structured data, sitemap
     * and seller gate all read it. Re-checking the application row here made the
     * storefront disagree with the directory whenever the two drifted apart.
     */
    $isVerified = $page->verified_at !== null;

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
@else
  <span
    class="inline-flex items-center gap-1 rounded-[var(--radius-pill)] border border-[var(--warn-edge)] bg-[var(--warn-wash)] px-2.5 py-1 text-[11px] font-semibold text-[var(--warn-text)]"
    title="This business has not been verified by mypage.ng"
  >
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path fill-rule="evenodd" d="M9.4 2.4a.7.7 0 011.2 0l7.1 12.3a.7.7 0 01-.6 1.1H2.9a.7.7 0 01-.6-1.1L9.4 2.4zM10 7a.8.8 0 01.8.8v3.1a.8.8 0 11-1.6 0V7.8A.8.8 0 0110 7zm0 6.2a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
    </svg>
    Not verified by mypage.ng
  </span>
@endif
