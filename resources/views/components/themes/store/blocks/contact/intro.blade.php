@props(['page', 'block'])

@php
    $intro = $block->field('intro');
    $where = collect([$page->city, $page->state])->filter()->join(', ');
@endphp

<section class="shell pt-24 pb-10 md:pt-28">
  <nav class="flex items-center gap-2 text-xs text-[var(--ink-3)] mb-6" aria-label="Breadcrumb">
    <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)] transition-colors">Home</a>
    <span aria-hidden="true">/</span>
    <span class="text-[var(--ink)] font-semibold" aria-current="page">Contact</span>
  </nav>

  <div class="max-w-2xl">
    @if ($where)
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold mb-4">
        <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
        {{ $where }} &amp; Direct WhatsApp
      </span>
    @endif

    <h1 class="t-display-1 text-[var(--ink)]">{{ $intro ? 'Contact' : 'Visit the Studio or Reach the Seller.' }}</h1>

    @if ($intro)
      <p class="t-body-lg text-[var(--ink-2)] mt-4">{{ $intro }}</p>
    @else
      <p class="t-body-lg text-[var(--ink-2)] mt-4">
        Whether you need a fitting, a quote for a custom order, or an update on a delivery, our team responds swiftly.
      </p>
    @endif
  </div>
</section>