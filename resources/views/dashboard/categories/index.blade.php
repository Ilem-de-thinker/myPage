@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Categories"
        :subtitle="$categories->total().' categor'.($categories->total() === 1 ? 'y' : 'ies').' in your store'"
    >
        <a href="{{ route('categories.create') }}" class="px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-xs">+ Add category</a>
    </x-ui.page-header>

    <x-ui.data-table
        :rows="$categories"
        :columns="[
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'products_count', 'label' => 'Products'],
        ]"
        search-placeholder="Search categories…"
        :bulk-destroy-route="route('categories.bulk-destroy')"
        edit-route="categories.edit"
        destroy-route="categories.destroy"
        empty-text="No categories yet. Add one if your catalogue is large enough to need filtering."
    />
@endsection