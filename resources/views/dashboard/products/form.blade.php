@extends('layouts.app')

@section('content')
    <x-ui.page-header
        :title="$product ? 'Edit product' : 'Add product'"
        :subtitle="$product ? $product->name : 'Products appear on your storefront once marked active.'"
        :back="$product ? route('products.index') : null"
        back-label="Back to products"
        width="max-w-2xl mx-auto"
    >
        @if ($product)
            <a href="{{ route('products.variants.index', $product) }}" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-600">
                Variants ({{ $product->variants->count() }})
            </a>
        @endif
    </x-ui.page-header>

    <form method="POST" action="{{ $product ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded-2xl border border-slate-200 space-y-5 max-w-2xl mx-auto">
        @csrf
        @if ($product) @method('PUT') @endif

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Name</label>
            <input type="text" name="name" value="{{ old('name', $product?->name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
            @error('name') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ newCat: false, name: '', error: '', busy: false }">
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold text-slate-700">Category</label>
                <button type="button" @click="newCat = true" class="text-[11px] font-semibold text-brand hover:text-brand-dark">+ New category</button>
            </div>
            <select name="category_id" x-ref="catSelect" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
                <option value="">— None —</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('category_id', $product?->category_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>

            <div x-cloak x-show="newCat" x-transition class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @keydown.escape.window="newCat = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl" @click.outside="newCat = false">
                    <h3 class="font-display font-bold text-ink mb-3">New category</h3>
                    <input type="text" x-model="name" placeholder="e.g. Fabrics" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm mb-2">
                    <p x-show="error" x-text="error" class="text-red-600 text-[11px] mb-2"></p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="newCat = false" class="px-4 py-2 text-xs font-semibold text-slate-500">Cancel</button>
                        <button type="button" :disabled="busy" @click="busy = true; error = '';
                            fetch('{{ route('categories.store') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({ name })
                            }).then(async r => {
                                if (!r.ok) { error = (await r.json()).message ?? 'Could not create category'; busy = false; return; }
                                const cat = await r.json();
                                const opt = new Option(cat.name, cat.id, true, true);
                                $refs.catSelect.add(opt);
                                name = ''; newCat = false; busy = false;
                            }).catch(() => { error = 'Network error'; busy = false; })"
                            class="px-4 py-2 rounded-xl bg-brand text-white text-xs font-semibold">Create</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Price (₦)</label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product ? $product->price_kobo / 100 : '') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
                @error('price') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">SKU <span class="text-slate-400 font-normal">(auto if blank)</span></label>
                <input type="text" name="sku" value="{{ old('sku', $product?->sku) }}" placeholder="Auto-generated" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Description</label>
            <textarea name="description" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">{{ old('description', $product?->description) }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Status</label>
            @foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $v => $label)
                <label class="inline-flex items-center gap-1.5 mr-4 text-sm">
                    <input type="radio" name="status" value="{{ $v }}" @checked(old('status', $product?->status ?? 'draft') === $v)> {{ $label }}
                </label>
            @endforeach
        </div>

        <div class="flex items-center gap-6">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="track_stock" value="1" @checked($product?->track_stock)> Track stock
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_featured" value="1" @checked($product?->is_featured)> Featured
            </label>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Stock quantity</label>
            <input type="number" name="stock_qty" min="0" value="{{ old('stock_qty', $product?->stock_qty ?? 0) }}" class="w-32 px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
        </div>

        @php $savedImages = $product?->images?->map(fn ($img) => \Illuminate\Support\Facades\Storage::url($img->path))->all() ?? []; @endphp
        <div x-data="{ previewOpen: false, previews: @js($savedImages) }">
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Images</label>
            <input type="file" name="images[]" multiple accept="image/*" class="text-sm"
                   @change="previews = [...previews, ...Array.from($event.target.files).map(f => URL.createObjectURL(f))]">
            <button type="button" @click="previewOpen = true" :disabled="previews.length === 0"
                    class="mt-2 text-xs font-semibold text-brand disabled:text-slate-300 disabled:cursor-not-allowed">Preview images</button>
            <div x-cloak x-show="previewOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @keydown.escape.window="previewOpen = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-display font-bold text-ink">Image preview</h3>
                        <button type="button" @click="previewOpen = false" class="text-slate-400 text-lg leading-none">&times;</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="(src, i) in previews" :key="i">
                            <img :src="src" class="w-full h-32 object-cover rounded-xl border border-slate-200">
                        </template>
                    </div>
                </div>
            </div>
            @if (! empty($savedImages))
                <div class="flex gap-2 mt-2">
                    @foreach ($savedImages as $url)
                        <img src="{{ $url }}" class="w-16 h-16 object-cover rounded-lg">
                    @endforeach
                </div>
            @endif
        </div>

        <div class="pt-2 flex justify-end gap-3">
            <a href="{{ route('products.index') }}" class="px-4 py-2.5 text-sm font-semibold text-slate-500">Cancel</a>
            <button class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-sm">{{ $product ? 'Save changes' : 'Add product' }}</button>
        </div>
    </form>
@endsection
