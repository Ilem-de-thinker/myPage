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
        <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}" class="inline-block mt-6 text-sm font-semibold" style="color: var(--brand)">See all products →</a>
    @elseif (empty($products))
        @if (! empty($section->content['image']))
            <img src="{{ \Illuminate\Support\Facades\Storage::url($section->content['image']) }}" alt="" class="rounded-xl my-4 max-w-full">
        @endif
    @else
        <p class="text-sm text-slate-500">No products to show yet.</p>
    @endif
</section>