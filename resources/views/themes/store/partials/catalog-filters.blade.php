{{--
    The store catalogue's filter panel, rendered twice.

    Included by the desktop sticky rail and by the mobile off-canvas drawer, from
    the same data, so the two cannot drift. The only thing that differs is where
    the panel sits — which is why the panel itself carries no positioning.

    @var \App\Domains\Catalog\Http\Requests\ProductFiltersRequest $filters
    @var \Illuminate\Support\Collection $categories
    @var array{min: int, max: int} $priceBounds
    @var \App\Domains\Seller\Models\StorePage $page
    @var string $idPrefix  a namespace so two copies on one page keep separate ids
--}}
@php
    use App\Shared\Support\Money;

    $action = \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]);
    $selected = $filters->categoryIds();
    // Naira in the boxes, because that is the unit the labels are written in.
    $minNaira = $filters->input('min');
    $maxNaira = $filters->input('max');
    $floorNaira = $priceBounds['min'] > 0 ? Money::format($priceBounds['min']) : '0';
    $ceilingNaira = $priceBounds['max'] > 0 ? Money::format($priceBounds['max']) : '0';
@endphp

<form method="GET" action="{{ $action }}"
      x-data="productFilters(@jsExpr(['open' => $categories->isNotEmpty(), 'q' => $filters->term() ?? '']))"
      @input.debounce.350ms="search()"
      class="space-y-6">

  {{-- Everything the panel owns is a real input. The panel is progressive
       enhancement on top of a plain GET form: the Search box submits on Enter and
       the Apply button submits on tap, so a no-JS visitor filters the same page a
       JS visitor does. The debounce only removes the wait. --}}

  <div>
    <label for="{{ $idPrefix }}-q" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
      Search Catalogue
    </label>
    <div class="relative">
      <input type="search"
             id="{{ $idPrefix }}-q"
             name="q"
             x-model="q"
             placeholder="Search products"
             autocomplete="off"
             class="w-full min-h-11 pl-3 pr-9 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">

      {{-- Always in the DOM, shown by the model rather than by the server: a
           clear button that only appears after a round trip is a button that is
           not there when you need it. Cancels the pending debounce first, so a
           clear cannot be undone by the search it was clearing. --}}
      <button type="button" x-show="q" x-cloak
              @click="clearSearch()"
              aria-label="Clear search"
              class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-[var(--ink-3)] hover:text-[var(--ink)]">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
    </div>
  </div>

  {{-- The sort rides in the same form: changing the order should not throw away
       the categories and price the buyer just picked. --}}
  <div>
    <label for="{{ $idPrefix }}-sort" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
      Sort By
    </label>
    <select id="{{ $idPrefix }}-sort" name="sort"
            class="w-full min-h-11 px-3 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
      @foreach ($sorts as $sort)
        <option value="{{ $sort->value }}" @selected($filters->sort() === $sort)>{{ $sort->label() }}</option>
      @endforeach
    </select>
  </div>

  {{-- A real <details> rather than an Alpine disclosure: the category list is
       open and closed by the browser, so it survives a failed script and stays
       keyboard operable for free. --}}
  @if ($categories->isNotEmpty())
    <details class="group border-y border-[var(--rule)] py-3" open>
      <summary class="flex items-center justify-between cursor-pointer list-none text-xs font-bold text-[var(--ink)]">
        <span>Categories</span>
        <span class="text-[10px] text-[var(--ink-3)] group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
      </summary>

      <div class="mt-3 space-y-2 text-xs">
        @foreach ($categories as $category)
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="category[]" value="{{ $category->id }}"
                   @checked(in_array($category->id, $selected, true))
                   class="rounded border-[var(--rule)] accent-[var(--brand)] focus:ring-[var(--focus-ring)]">
            {{-- The count, so a buyer can see which category is worth opening
                 before opening it. --}}
            <span class="flex-1 truncate">{{ $category->name }} ({{ $category->products_count }})</span>
          </label>
        @endforeach
      </div>
    </details>
  @endif

  @if ($priceBounds['max'] > 0)
    {{-- Two number boxes rather than a dual slider. A two-handle slider needs a
         pointer, a keyboard model and a drag state; on a phone it fights the page
         scroll. Two boxes work with a thumb, a keyboard and a screen reader, and
         the range is stated above them anyway. --}}
    <fieldset>
      <legend class="text-xs font-bold text-[var(--ink)] mb-2">Price Range (₦)</legend>
      <p class="t-meta mb-2">This shop runs {{ $floorNaira }} to {{ $ceilingNaira }}.</p>

      <div class="grid grid-cols-2 gap-2">
        <div>
          <label for="{{ $idPrefix }}-min" class="t-meta block mb-1">Min (₦)</label>
          <input type="number"
                 id="{{ $idPrefix }}-min"
                 name="min"
                 inputmode="numeric"
                 min="0"
                 step="100"
                 value="{{ $minNaira }}"
                 placeholder="{{ ltrim($floorNaira, '₦ ') }}"
                 class="w-full min-h-11 px-2 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] font-mono tabular-nums focus:border-[var(--brand)]">
        </div>

        <div>
          <label for="{{ $idPrefix }}-max" class="t-meta block mb-1">Max (₦)</label>
          <input type="number"
                 id="{{ $idPrefix }}-max"
                 name="max"
                 inputmode="numeric"
                 min="0"
                 step="100"
                 value="{{ $maxNaira }}"
                 placeholder="{{ ltrim($ceilingNaira, '₦ ') }}"
            class="w-full min-h-11 px-2 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] font-mono tabular-nums focus:border-[var(--brand)]">
        </div>
      </div>
    </fieldset>
  @endif

  <div class="pt-3 border-t border-[var(--rule)] flex gap-2">
    <button type="submit"
                 class="flex-1 min-h-11 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs hover:opacity-90 transition-opacity">
      Apply Filters
    </button>

    {{-- Only offered when something is on: a Clear button on an unfiltered
         catalogue is a control that does nothing. --}}
    @if ($filters->hasFilters())
      <a href="{{ $action }}"
         class="px-3 min-h-11 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink)] font-semibold text-xs flex items-center justify-center hover:bg-[var(--surface)] transition-colors">
        Clear
      </a>
    @endif
  </div>
</form>
