@php
    use App\Domains\Catalog\Models\Category;

    $categories = $page->categories()
        ->whereHas('products', fn ($query) => $query->active())
        ->orderBy('position')
        ->orderBy('name')
        ->get();

    $counts = Category::query()
        ->where('store_page_id', $page->id)
        ->withCount(['products as products_count' => fn ($query) => $query->active()])
        ->pluck('products_count', 'id');
@endphp

@if ($categories->isNotEmpty())
  {{--
    One card per collection, each opening on the plate the seller's own products
    give it. A category with nothing under it is not offered at all: a card that
    leads to an empty shelf is worse than no card.
  --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    @foreach ($categories as $category)
      @php $lead = $category->products()->with('images')->active()->orderByDesc('id')->first(); @endphp
      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug, 'category' => $category->slug]) }}"
         class="card p-4 group">
        <div class="plate aspect-[4/3] mb-3">
          @if ($lead?->images->isNotEmpty())
            <img src="{{ \Illuminate\Support\Facades\Storage::url($lead->images->first()->path) }}"
                 alt="{{ $category->name }}"
                 width="400"
                 height="300"
                 loading="lazy"
                 class="w-full h-full object-cover">
          @endif
          <div class="grain" aria-hidden="true"></div>
        </div>

        <h3 class="text-sm font-bold text-[var(--ink)] group-hover:text-[var(--brand-text)] transition-colors">
          {{ $category->name }}
        </h3>
        <p class="t-meta mt-1">
          {{ $counts[$category->id] ?? 0 }} {{ \Illuminate\Support\Str::plural('Piece', $counts[$category->id] ?? 0) }}
        </p>
      </a>
    @endforeach
  </div>
@endif