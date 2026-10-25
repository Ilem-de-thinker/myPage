@props(['page', 'block'])

@php
    $heading = $block->field('heading') ?: 'Shop by category';
@endphp

<section class="section-y border-b border-[var(--rule)]">
  <div class="shell">
    <div class="flex items-center justify-between mb-8 gap-4">
      <div>
        <p class="t-meta">Collections</p>
        <h2 class="t-display-2 text-[var(--ink)] mt-1">{{ $heading }}</h2>
      </div>

      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
         class="text-xs font-bold text-[var(--brand-text)] hover:underline shrink-0">
        View all categories →
      </a>
    </div>

    <x-themes.store.blocks.home._categories :page="$page" :block="$block" />
  </div>
</section>