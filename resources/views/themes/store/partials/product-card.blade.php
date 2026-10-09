@php
    $image = $product->images->first();
@endphp
<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col">
    <a href="{{ route('storefront.product', ['slug' => $page->slug, 'productSlug' => $product->slug]) }}">
        <div class="aspect-square bg-slate-100">
            @if ($image)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($image->path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @endif
        </div>
        <div class="p-4">
            <p class="font-semibold text-ink text-sm">{{ $product->name }}</p>
            <p class="text-sm font-bold mt-1" style="color: var(--brand)">{{ $product->priceLabel }}</p>
            @if ($product->track_stock && $product->stock_qty < 1)
                <p class="text-[11px] text-red-600 mt-1">Out of stock</p>
            @endif
        </div>
    </a>
    <div class="px-4 pb-4 mt-auto">
        <button type="button" @click="$store.cart.add({ id: {{ $product->id }}, name: @js($product->name), price_kobo: {{ $product->price_kobo }} })"
                class="w-full px-4 py-2.5 rounded-xl border text-xs font-semibold"
                style="border-color: var(--brand); color: var(--brand)"
                @disabled($product->track_stock && $product->stock_qty < 1)>
            <span x-show="!$store.cart.items.some(i => i.id === {{ $product->id }})">Add to cart</span>
            <span x-show="$store.cart.items.some(i => i.id === {{ $product->id }})">In cart</span>
        </button>
    </div>
</div>