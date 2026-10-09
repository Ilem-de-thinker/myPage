@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Products" :subtitle="$products->total().' product(s) in your store'">
        <a href="{{ route('products.create') }}" class="px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-xs">+ Add product</a>
    </x-ui.page-header>

    <x-ui.data-table
        :rows="$products"
        :columns="[
            ['key' => 'primaryImageUrl', 'label' => '', 'type' => 'image'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'priceLabel', 'label' => 'Price'],
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
    />
@endsection