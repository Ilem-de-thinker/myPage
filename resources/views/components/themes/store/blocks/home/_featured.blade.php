@php
    use App\Domains\Catalog\Models\Product;

    // No products, no section. A new shop should not show a heading above an
    // empty grid the moment it goes live.
    $items = Product::query()
        ->with(['images', 'category'])->active()
        ->where('store_page_id', $page->id)
        ->latest()->limit(4)->get();

    // One product is a feature, not a four-across grid with three holes in it.
    $count = $items->count();
    $preset = $page->settings['theme_preset'] ?? 'catalog';
@endphp

@if ($count > 0)
  {{--
    The same card the catalogue draws, at the same fidelity: a seller who
    promoted a piece here sees it exactly as a shopper would meet it on the
    products page.
  --}}
  <div data-collection-style="{{ $preset }}" class="theme-grid theme-grid-featured grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    @foreach ($items as $item)
      @include('themes.store.partials.product-card', ['product' => $item, 'page' => $page])
    @endforeach
  </div>
@endif
