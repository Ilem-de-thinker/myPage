@extends('layouts.app')

@section('content')
    <x-ui.page-header
        :title="$variant ? 'Edit variant' : 'Add variant'"
        :subtitle="'For '.$product->name"
        :back="route('products.variants.index', $product)"
        back-label="Back to variants"
        width="max-w-2xl mx-auto"
    />

    <form method="POST"
          action="{{ $variant ? route('variants.update', $variant) : route('products.variants.store', $product) }}"
          enctype="multipart/form-data"
          class="bg-white p-6 rounded-2xl border border-slate-200 space-y-5 max-w-2xl mx-auto"
          x-data="{ previewOpen: false, previews: @js($variant?->imageUrl() ? [$variant->imageUrl()] : []) }">
        @csrf
        @if ($variant) @method('PUT') @endif

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Variant name</label>
            <input type="text" name="name" value="{{ old('name', $variant?->name) }}" required placeholder="e.g. Large / Red"
                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
            @error('name') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Price (₦) <span class="text-slate-400 font-normal">(blank = product price)</span></label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $variant?->price_kobo ? $variant->price_kobo / 100 : '') }}"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
                @error('price') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Stock</label>
                <input type="number" min="0" name="stock_qty" value="{{ old('stock_qty', $variant?->stock_qty ?? 0) }}"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">SKU <span class="text-slate-400 font-normal">(auto if blank)</span></label>
            <input type="text" name="sku" value="{{ old('sku', $variant?->sku) }}" placeholder="Auto-generated"
                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
            @error('sku') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Photo</label>
            <input type="file" name="image" accept="image/*" class="text-sm"
                   @change="previews = [...previews, ...Array.from($event.target.files).map(f => URL.createObjectURL(f))]">
            <button type="button" @click="previewOpen = true" :disabled="previews.length === 0"
                    class="mt-2 text-xs font-semibold text-brand disabled:text-slate-300 disabled:cursor-not-allowed">Preview photo</button>
            <div x-cloak x-show="previewOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @keydown.escape.window="previewOpen = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-display font-bold text-ink">Photo preview</h3>
                        <button type="button" @click="previewOpen = false" class="text-slate-400 text-lg leading-none">&times;</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="(src, i) in previews" :key="i">
                            <img :src="src" class="w-full h-32 object-cover rounded-xl border border-slate-200">
                        </template>
                    </div>
                </div>
            </div>
            @if ($variant?->imageUrl())
                <img src="{{ $variant->imageUrl() }}" class="w-20 h-20 object-cover rounded-lg mt-3">
            @endif
        </div>

        <div class="pt-2 flex justify-end gap-3">
            <a href="{{ route('products.variants.index', $product) }}" class="px-4 py-2.5 text-sm font-semibold text-slate-500">Cancel</a>
            <button class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-sm">
                {{ $variant ? 'Save variant' : 'Add variant' }}
            </button>
        </div>
    </form>
@endsection