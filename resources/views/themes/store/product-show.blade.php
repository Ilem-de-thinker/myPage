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

@php
            $variants = $product->variants->sortBy('name')->values();
            $options = $variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price_kobo' => $variant->priceKobo(),
                'stock' => $variant->stock_qty,
            ])->all();
        @endphp

        <div class="mt-6 flex flex-wrap items-center gap-3"
             x-data="{
                 qty: 1,
                 variantId: {{ $variants->isEmpty() ? 'null' : $options[0]['id'] }},
                 options: @js($options),
                 get variant() { return this.options.find((o) => o.id === this.variantId) ?? null; },
                 get unitKobo() { return this.variant ? this.variant.price_kobo : {{ $product->price_kobo }}; },
                 get priceLabel() {
                     return '₦' + (this.unitKobo / 100).toLocaleString(undefined, {
                         minimumFractionDigits: this.unitKobo % 100 ? 2 : 0,
                         maximumFractionDigits: 2,
                     });
                 },
                 get maxQty() {
                     return this.variant ? this.variant.stock : {{ $product->track_stock ? $product->stock_qty : 99 }};
                 },
                 addToCart() {
                     $store.cart.add({
                         id: {{ $product->id }},
                         variant_id: this.variantId,
                         variant_name: this.variant ? this.variant.name : null,
                         name: @js($product->name),
                         price_kobo: this.unitKobo,
                     }, this.qty);
                 },
                 inCart() {
                     return $store.cart.items.some((line) => line.id === {{ $product->id }} && line.variant_id === this.variantId);
                 },
             }">

          @if ($variants->isNotEmpty())
            <div class="w-full">
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Options</label>
              <div class="flex flex-wrap gap-2">
                <template x-for="option in options" :key="option.id">
                  <button type="button"
                          @click="variantId = option.id; qty = 1"
                          :class="variantId === option.id ? 'ring-2' : ''"
                          :style="variantId === option.id ? 'border-color: var(--brand); color: var(--brand)' : ''"
                          :disabled="option.stock < 1"
                          class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-text="option.name"></span>
                    <span class="block text-[10px] font-normal text-slate-400" x-text="option.stock < 1 ? 'Sold out' : '₦' + ((option.price_kobo || 0) / 100).toLocaleString()"></span>
                  </button>
                </template>
              </div>
              <p class="text-[11px] text-slate-400 mt-1.5">
                <span x-text="maxQty"></span> available for this option
              </p>
            </div>
          @endif

          <div class="flex items-center gap-2">
            <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-10 h-10 rounded-xl border border-slate-300 text-slate-600">−</button>
            <span class="w-8 text-center text-sm font-semibold" x-text="qty"></span>
            <button type="button" @click="qty = Math.min(maxQty, qty + 1)" class="w-10 h-10 rounded-xl border border-slate-300 text-slate-600">+</button>
          </div>

          <button type="button" @click="addToCart()" :disabled="maxQty < 1"
                  class="px-6 py-3 rounded-xl text-white font-semibold text-sm disabled:opacity-50" style="background: var(--brand)">
            <span x-show="!inCart()">Add to cart</span>
            <span x-show="inCart()">Add more</span>
          </button>

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