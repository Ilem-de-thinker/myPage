@php
    use App\Domains\Catalog\Models\Product;

    /*
      The controller already resolved the page's eight newest products, with their
      images, for the home page's structured data — so they are reused here rather
      than queried a second time. A block rendered on its own, which is how the
      seller preview draws it, falls back to the same eight.
    */
    $items = $products ?? Product::query()
        ->with(['images', 'category'])->active()
        ->where('store_page_id', $page->id)
        ->latest()->limit(8)->get();

    $count = $items->count();
@endphp

@if ($count > 0)
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    @foreach ($items as $item)
      @include('themes.store.partials.product-card', ['product' => $item, 'page' => $page])
    @endforeach
  </div>
@endif