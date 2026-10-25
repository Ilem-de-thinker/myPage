@props(['page', 'block'])

@php
    $intro = $block->field('intro');
    $hours = $block->field('hours');
    $where = $page->locationLabel();
    $storefront = ['slug' => $page->slug];
@endphp

<div class="shell py-12">

  {{-- Breadcrumbs --}}
  <nav class="flex items-center gap-2 text-xs text-[var(--ink-3)] mb-6" aria-label="Breadcrumb">
    <a href="{{ \App\Shared\Support\StoreUrl::homePath($page) }}" class="hover:text-[var(--ink)] transition-colors">Home</a>
    <span aria-hidden="true">/</span>
    <span class="hover:text-[var(--ink)] transition-colors">Contact</span>
    <span aria-hidden="true">/</span>
    <span class="text-[var(--ink)] font-semibold" aria-current="page">Studio Location &amp; Hours</span>
  </nav>

  {{-- Page header --}}
  <div class="max-w-2xl mb-12">
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold mb-4">
      <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
      {{ $where ? $where.' Atelier & Direct WhatsApp' : 'Direct WhatsApp' }}
    </span>

    <h1 class="t-display-1 text-[var(--ink)]">Studio Location &amp; Concierge.</h1>

    <p class="t-body-lg text-[var(--ink-2)] mt-4">
      @if ($intro)
        {{ $intro }}
      @else
        Come to {{ $page->name }}{{ $where ? ' in '.$where : '' }}. Review the opening hours below, then reach the studio desk directly on WhatsApp.
      @endif
    </p>
  </div>

  {{--
    Hours the seller typed by hand, because hours are what somebody arrives on this
    page to find and they should never have to infer them from an address. The
    structured weekly hours, if the seller has set any, sit beside them lower down.
  --}}
  @if ($hours)
    <div class="mb-10 border-t border-[var(--rule)] pt-5">
      <p class="t-meta">When we are open</p>
      <p class="t-body mt-1.5 whitespace-pre-line">{{ $hours }}</p>
    </div>
  @endif
</div>