@props(['page', 'block'])

@php
    $heading = $block->field('heading') ?: 'Featured garments';
@endphp

{{--
  The heading is optional but the rows are not: a seller with three products and
  no typed heading still sells three products. So the section always renders and
  only the heading is conditional.

  It stays on the left. A centred heading above a left-aligned grid is a
  mistake, not a layout.
--}}
<section class="section-y border-b border-[var(--rule)]">
  <div class="shell">
    <div class="flex items-center justify-between mb-8 gap-4">
      <div>
        <p class="t-meta">Signature Pieces</p>
        <h2 class="t-display-2 text-[var(--ink)] mt-1">{{ $heading }}</h2>
      </div>

      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
         class="text-xs font-bold text-[var(--brand-text)] hover:underline shrink-0">
        View full catalogue →
      </a>
    </div>

    <x-themes.store.blocks.home._featured :page="$page" :block="$block" />
  </div>
</section>