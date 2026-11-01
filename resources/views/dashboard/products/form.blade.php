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
            <a href="{{ route('products.variants.index', $product) }}" class="px-4 py-2.5 rounded-xl border border-rule-strong text-xs font-semibold text-ink-2">
                Variants ({{ $product->variants->count() }})
            </a>
        @endif
    </x-ui.page-header>

    <form method="POST" action="{{ $product ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" class="bg-card p-6 rounded-2xl border border-rule space-y-5 max-w-2xl mx-auto" x-data="aiListingForm(@jsExpr([
        'name' => old('name', $product?->name ?? ''),
        'description' => old('description', $product?->description ?? ''),
        'task' => 'listing_copy',
        'feature' => 'products',
        'endpoint' => route('dashboard.ai.run'),
        'csrf' => csrf_token(),
    ]))">
        @csrf
        @if ($product) @method('PUT') @endif

        <div>
            <div class="mb-1.5 flex items-center justify-between gap-3">
                <label class="block text-xs font-semibold text-ink">Name</label>
                <button type="button" @click="draftCopy" :disabled="aiLoading || !name && !description" class="rounded-lg border border-brand-edge bg-brand-wash px-2.5 py-1.5 text-[11px] font-bold text-brand-text disabled:opacity-50"><span x-text="aiLoading ? 'Drafting…' : 'Draft with Mercho'"></span></button>
            </div>
            <input type="text" name="name" x-model="name" required class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
            @error('name') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div x-data="newCategoryForm(@jsExpr(['endpoint' => route('categories.store'), 'csrf' => csrf_token()]))">
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold text-ink">Category</label>
                <button type="button" @click="newCat = true" class="text-[11px] font-semibold text-brand hover:text-brand-text">+ New category</button>
            </div>
            <select name="category_id" x-ref="catSelect" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm">
                <option value="">— None —</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('category_id', $product?->category_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>

            <div x-cloak x-show="newCat" x-transition class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @keydown.escape.window="newCat = false">
                <div class="bg-card rounded-2xl p-6 w-full max-w-sm shadow-xl" @click.outside="newCat = false">
                    <h3 class="font-display font-bold text-ink mb-3">New category</h3>
                    <input type="text" x-model="name" placeholder="e.g. Fabrics" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm mb-2">
                    <p x-show="error" x-text="error" class="text-danger-text text-[11px] mb-2"></p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="newCat = false" class="px-4 py-2 text-xs font-semibold text-ink-3">Cancel</button>
                        <button type="button" :disabled="busy" @click="create()"
                            class="px-4 py-2 rounded-xl bg-brand text-white text-xs font-semibold">Create</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">Price (₦)</label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product ? $product->price_kobo / 100 : '') }}" required class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                @error('price') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">SKU <span class="text-ink-3 font-normal">(auto if blank)</span></label>
                <input type="text" name="sku" value="{{ old('sku', $product?->sku) }}" placeholder="Auto-generated" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
            </div>
        </div>

        <div class="rounded-2xl border border-rule bg-tint/40 p-4">
            <h2 class="text-xs font-semibold text-ink">Sale pricing <span class="font-normal text-ink-3">(optional)</span></h2>
            <p class="mt-1 text-[11px] leading-5 text-ink-3">
                The price above is what the buyer pays. Enter the normal price here to show it crossed out while the sale runs.
                Leave the window blank to keep the sale on until you remove the original price.
            </p>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">Original price (₦)</span>
                    <input type="number" step="0.01" min="0" name="compare_at"
                           value="{{ old('compare_at', $product?->compare_at_kobo ? $product->compare_at_kobo / 100 : '') }}"
                           placeholder="Leave blank for no sale"
                           class="mt-1 w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                    @error('compare_at') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Starts</span>
                    <input type="datetime-local" name="sale_starts_at"
                           value="{{ old('sale_starts_at', $product?->sale_starts_at?->format('Y-m-d\TH:i')) }}"
                           class="mt-1 w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                    <span class="mt-1 block text-[11px] text-ink-3">Blank means already started.</span>
                    @error('sale_starts_at') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Ends</span>
                    <input type="datetime-local" name="sale_ends_at"
                           value="{{ old('sale_ends_at', $product?->sale_ends_at?->format('Y-m-d\TH:i')) }}"
                           class="mt-1 w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                    <span class="mt-1 block text-[11px] text-ink-3">Blank means no end date.</span>
                    @error('sale_ends_at') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink mb-1.5">Description</label>
            <textarea name="description" x-model="description" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm"></textarea>
            <p x-show="aiMessage" x-text="aiMessage" class="mt-2 text-[11px] text-ok-text"></p>
            <p x-show="aiError" x-text="aiError" class="mt-2 text-[11px] text-danger-text"></p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink mb-1.5">Status</label>
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
            <label class="block text-xs font-semibold text-ink mb-1.5">Stock quantity</label>
            <input type="number" name="stock_qty" min="0" value="{{ old('stock_qty', $product?->stock_qty ?? 0) }}" class="w-32 px-4 py-2.5 rounded-xl border border-rule-strong text-sm">
        </div>

        <div class="rounded-2xl border border-rule bg-tint/40 p-4">
            <label class="block text-xs font-semibold text-ink mb-1.5">How this product reaches the customer</label>
            <select name="delivery_mode" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm">
                <option value="">Use my store delivery settings</option>
                <option value="delivery" @selected(old('delivery_mode', $product?->delivery_mode?->value) === 'delivery')>Deliver to the customer</option>
                <option value="pickup" @selected(old('delivery_mode', $product?->delivery_mode?->value) === 'pickup')>Pickup only</option>
            </select>

            <label class="mt-4 block text-xs font-semibold text-ink mb-1.5">Delivery fee override (₦)</label>
            <input type="number" name="delivery_fee" min="0" step="0.01" inputmode="decimal"
                   value="{{ old('delivery_fee', $product?->delivery_fee_kobo ? number_format($product->delivery_fee_kobo / 100, 2, '.', '') : '') }}"
                   placeholder="Leave blank to use the area fee"
                   class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm">
            <span class="mt-1 block text-[11px] text-ink-3">
                Set this only if this product costs more or less to deliver than the rest of your shop.
            </span>
            @error('delivery_mode') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
            @error('delivery_fee') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
        </div>

        @php $savedImages = $product?->images?->map(fn ($img) => \Illuminate\Support\Facades\Storage::url($img->path))->all() ?? []; @endphp
        <div x-data="imagePreview(@jsExpr(['previews' => $savedImages]))">
            <label class="block text-xs font-semibold text-ink mb-1.5">Images</label>
            <input type="file" name="images[]" multiple accept="image/*" class="text-sm"
                   @change="addFiles($event)">
            <button type="button" @click="previewOpen = true" :disabled="previews.length === 0"
                    class="mt-2 text-xs font-semibold text-brand disabled:text-ink-3 disabled:cursor-not-allowed">Preview images</button>
            <div x-cloak x-show="previewOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @keydown.escape.window="previewOpen = false">
                <div class="bg-card rounded-2xl p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-display font-bold text-ink">Image preview</h3>
                        <button type="button" @click="previewOpen = false" class="text-ink-3 text-lg leading-none">&times;</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="(src, i) in previews" :key="i">
                            <img :src="src" class="w-full h-32 object-cover rounded-xl border border-rule">
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
            <a href="{{ route('products.index') }}" class="px-4 py-2.5 text-sm font-semibold text-ink-3">Cancel</a>
            <button class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-sm">{{ $product ? 'Save changes' : 'Add product' }}</button>
        </div>
    </form>
@endsection
