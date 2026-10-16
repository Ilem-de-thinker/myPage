@props(['page', 'block', 'products' => null])

@php
    use App\Domains\Catalog\Models\Product;

    // The real total, for the link out. The grid below is a window onto the
    // catalogue, never the whole of it, and a count that described the window
    // would understate the shop.
    $total = Product::query()->active()->where('store_page_id', $page->id)->count();
@endphp

<section class="section-y border-b border-[var(--rule)]">
  <div class="shell">
    <div class="flex items-center justify-between mb-8 gap-4">
      <div>
        <p class="t-meta">Everything We Make</p>
        <h2 class="t-display-2 text-[var(--ink)] mt-1">{{ $block->field('heading') ?: 'The full catalogue' }}</h2>
      </div>

      <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
         class="text-xs font-bold text-[var(--brand-text)] hover:underline shrink-0">
        View full catalogue ({{ $total }} {{ \Illuminate\Support\Str::plural('piece', $total) }}) →
      </a>
    </div>

    <x-themes.store.blocks.home._products :page="$page" :block="$block" :products="$products" />
  </div>
</section>