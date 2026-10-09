<x-themes.shared.layout :page="$page">
  @php $image = $product->images->first(); @endphp
  <article class="max-w-5xl mx-auto px-4 py-8">
    <div class="grid md:grid-cols-2 gap-8">
      <div class="bg-slate-100 rounded-2xl overflow-hidden aspect-square">
        @if ($image)
          <img src="{{ \Illuminate\Support\Facades\Storage::url($image->path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
        @endif
      </div>

      <div>
        <h1 class="text-2xl font-bold" style="font-family: var(--font-heading-stack)">{{ $product->name }}</h1>
        @if ($product->category)
          <p class="text-xs text-slate-500 mt-1">{{ $product->category->name }}</p>
        @endif
        <p class="text-2xl font-extrabold mt-3" style="color: var(--brand)">{{ $product->priceLabel }}</p>
        <p class="text-[11px] text-slate-400 mt-1">SKU: {{ $product->sku }}</p>

        @if ($product->track_stock)
          <p class="text-xs mt-2 {{ $product->stock_qty > 0 ? 'text-emerald-700' : 'text-red-600' }}">
            {{ $product->stock_qty > 0 ? $product->stock_qty.' in stock' : 'Out of stock' }}
          </p>
        @endif

        @if ($product->description)
          <div class="prose max-w-none text-sm text-slate-700 mt-4">{!! \App\Shared\Support\Html::sanitize($product->description) !!}</div>
        @endif

        <div class="mt-6 flex flex-wrap items-center gap-3" x-data="{ qty: 1 }">
          <div class="flex items-center gap-2">
            <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-10 h-10 rounded-xl border border-slate-300 text-slate-600">−</button>
            <span class="w-8 text-center text-sm font-semibold" x-text="qty"></span>
            <button type="button" @click="qty = qty + 1" class="w-10 h-10 rounded-xl border border-slate-300 text-slate-600">+</button>
          </div>
          <button type="button"
                  @click="$store.cart.add({ id: {{ $product->id }}, name: @js($product->name), price_kobo: {{ $product->price_kobo }} }, qty)"
                  class="px-6 py-3 rounded-xl text-white font-semibold text-sm" style="background: var(--brand)">Add to cart</button>
          <a href="{{ route('storefront.cart', ['slug' => $page->slug]) }}" class="text-xs font-semibold" style="color: var(--brand)">Go to cart →</a>
          @if ($page->whatsapp_number)
            <a href="{{ \App\Shared\Support\WhatsApp::productLink($page, $product) }}" target="_blank" rel="noopener"
               class="px-6 py-3 rounded-xl border border-slate-300 font-semibold text-sm text-slate-700 hover:bg-slate-50">Order on WhatsApp</a>
          @endif
        </div>
        <p class="text-xs text-slate-500 mt-2" x-show="$store.cart.count > 0" x-text="$store.cart.count + ' item(s) in your cart'"></p>
      </div>
    </div>

    @if ($related->isNotEmpty())
      <section class="mt-12">
        <h2 class="text-xl font-bold mb-4" style="font-family: var(--font-heading-stack)">You may also like</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          @foreach ($related as $item)
            @include('themes.store.partials.product-card', ['product' => $item, 'page' => $page])
          @endforeach
        </div>
      </section>
    @endif
  </article>
</x-themes.shared.layout>