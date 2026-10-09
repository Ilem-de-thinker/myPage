@props(['page', 'section', 'products' => null])
<section class="max-w-5xl mx-auto px-4 py-12">
  <h2 class="text-2xl font-bold mb-3" style="font-family: var(--font-heading-stack)">{{ $section->content['heading'] ?? \Illuminate\Support\Str::headline($section->key) }}</h2>
  @if ($section->content['body'] ?? null)
    <div class="prose max-w-none mb-6">{!! \App\Shared\Support\Html::sanitize($section->content['body']) !!}</div>
  @endif

  @if (! empty($products) && $products->isNotEmpty())
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      @foreach ($products as $product)
        @include('themes.store.partials.product-card', ['product' => $product, 'page' => $page])
      @endforeach
    </div>
  @endif
</section>