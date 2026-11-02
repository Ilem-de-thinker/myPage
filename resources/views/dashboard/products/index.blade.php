@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Products" :subtitle="$products->total().' product(s) in your store'">
        <div class="flex flex-wrap items-center gap-2"><button type="button" @click.stop="$dispatch('ai-open', { text: '' })" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-brand-edge bg-brand-wash px-4 py-2.5 text-xs font-semibold text-brand-text"><x-ui.icon name="spark" class="size-4" /> Use Mercho</button><a href="{{ route('products.create') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover"><x-ui.icon name="shopping-bag" class="size-4" /> Add product</a></div>
    </x-ui.page-header>

    <details class="mb-5 rounded-2xl border border-rule bg-card p-5">
        <summary class="cursor-pointer text-sm font-bold text-ink">Import products from CSV</summary>
        <p class="mt-2 text-xs text-ink-3">Required columns: <code>name, price</code>. Optional: <code>description, sku, status, stock_qty, category</code>.</p>
        <form method="POST" action="{{ route('products.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <label class="text-xs font-semibold text-ink-2">CSV file<input type="file" name="file" accept=".csv,text/csv" required class="mt-2 block min-h-11 rounded-xl border border-rule-strong bg-card px-3 py-2 text-xs text-ink"></label>
            <button class="min-h-11 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-brand-ink">Queue import</button>
        </form>
    </details>

    <x-ui.data-table
        :rows="$products"
        :columns="[
            ['key' => 'primaryImageUrl', 'label' => '', 'type' => 'image'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'priceLabel', 'label' => 'Price'],
            ['key' => 'saleLabel', 'label' => 'Sale'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
            ['key' => 'stock_qty', 'label' => 'Stock'],
            ['key' => 'sku', 'label' => 'SKU'],
        ]"
        :filters="[['name' => 'status', 'label' => 'All statuses', 'options' => ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']]]"
        search-placeholder="Search products…"
        :bulk-destroy-route="route('products.bulk-destroy')"
        edit-route="products.edit"
        destroy-route="products.destroy"
        stock-route="products.stock"
        extra-route="products.variants.index"
        extra-label="Variants"
        empty-text="No products yet. Add your first product."
    >
        <x-slot:bulkActions>
            <form method="POST" action="{{ route('products.generate-descriptions') }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <select name="tone" class="rounded-lg border border-rule-strong bg-card px-2 py-1.5 text-xs">
                    <option value="professional">Professional</option>
                    <option value="friendly">Friendly</option>
                    <option value="playful">Playful</option>
                    <option value="luxury">Luxury</option>
                    <option value="concise">Concise</option>
                </select>
                <select name="length" class="rounded-lg border border-rule-strong bg-card px-2 py-1.5 text-xs">
                    <option value="short">Short</option>
                    <option value="medium" selected>Medium</option>
                    <option value="long">Long</option>
                </select>
                <select name="language" class="rounded-lg border border-rule-strong bg-card px-2 py-1.5 text-xs">
                    <option>English</option>
                    <option>Nigerian Pidgin</option>
                    <option>Yoruba</option>
                    <option>Igbo</option>
                    <option>Hausa</option>
                </select>
                <label class="inline-flex items-center gap-1.5 text-xs text-ink-2">
                    <input type="checkbox" name="overwrite" value="1"> Overwrite existing
                </label>
                <button class="rounded-lg bg-brand px-3 py-1.5 text-xs font-bold text-brand-ink">Generate descriptions</button>
            </form>
        </x-slot:bulkActions>
    </x-ui.data-table>
@endsection
