<x-themes.shared.layout :page="$page">
  <section class="max-w-5xl mx-auto px-4 py-8">
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products…" class="flex-1 min-w-48 px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
      <select name="category" class="px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
        <option value="">All categories</option>
        @foreach ($categories as $category)
          <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
        @endforeach
      </select>
      <button class="px-5 py-2.5 rounded-xl text-white font-semibold text-sm" style="background: var(--brand)">Filter</button>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
      @forelse ($products as $product)
        @include('themes.store.partials.product-card', ['product' => $product, 'page' => $page])
      @empty
        <p class="col-span-full text-sm text-slate-500 py-10 text-center">No products here yet.</p>
      @endforelse
    </div>

    <div class="mt-6">{{ $products->links() }}</div>
  </section>
</x-themes.shared.layout>