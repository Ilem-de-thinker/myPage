@extends('layouts.app')

@section('content')
    <x-ui.page-header
        :title="'Variants: '.$product->name"
        subtitle="Each variant is saved on its own and can have its own price, stock and photo."
        :back="route('products.index')"
        back-label="Back to products"
    >
        <a href="{{ route('products.edit', $product) }}" class="px-4 py-2.5 rounded-xl border border-rule-strong text-xs font-semibold text-ink-2">Edit product</a>
        <a href="{{ route('products.variants.create', $product) }}" class="px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-xs">+ Add variant</a>
    </x-ui.page-header>

    <div class="max-w-2xl mx-auto">
        @if ($variants->isEmpty())
            <div class="bg-card rounded-2xl border border-rule p-10 text-center">
                <p class="text-sm text-ink-3 mb-4">No variants yet.</p>
                <p class="text-xs text-ink-3 mb-4">Add variants for sizes, colours or separate stock. Leave this empty if the product is sold as-is.</p>
                <a href="{{ route('products.variants.create', $product) }}" class="inline-block px-5 py-2.5 rounded-xl bg-brand text-white text-xs font-semibold">Add the first variant</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($variants as $variant)
                    <div class="bg-card rounded-2xl border border-rule overflow-hidden">
                        <div class="aspect-video bg-tint">
                            @if ($variant->imageUrl())
                                <img src="{{ $variant->imageUrl() }}" alt="{{ $variant->name }}" class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-sm text-ink">{{ $variant->name }}</p>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-tint text-ink-2">{{ $variant->stock_qty }} in stock</span>
                            </div>
                            <p class="text-sm font-bold mt-1" style="color: var(--brand)">
                                {{ $variant->priceKobo() ? \App\Shared\Support\Money::format($variant->priceKobo()) : 'Uses product price' }}
                            </p>
                            <p class="text-[11px] text-ink-3 mt-1">{{ $variant->sku }}</p>
                            <div class="flex items-center gap-3 mt-3">
                                <a href="{{ route('variants.edit', $variant) }}" class="text-xs font-semibold text-brand">Edit</a>
                                <form method="POST" action="{{ route('variants.destroy', $variant) }}" onsubmit="return confirm('Delete {{ $variant->name }}?')">@csrf @method('DELETE')
                                    <button class="text-xs font-semibold text-danger-text">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection