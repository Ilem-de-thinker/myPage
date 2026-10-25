@php
    /**
     * The card a shopper actually decides on.
     *
     * Shared by the catalogue grid, the home page and the "you may also like"
     * row, with no changes: the only difference between those layouts is the
     * column width, and a card that looked different in each would make them
     * feel like different shops.
     *
     * @var \App\Domains\Catalog\Models\Product $product
     * @var \App\Domains\Seller\Models\StorePage $page
     */
    $image = $product->images->first();
    $soldOut = $product->isSoldOut();
    $onSale = $product->isDiscounted();
    $category = $product->category;
    // Populated by ProductSort::Popular; absent on every other ordering.
    $sold = isset($product->sold_count) ? (int) $product->sold_count : null;
    $href = \App\Shared\Support\StoreUrl::path('storefront.product', ['slug' => $page->slug, 'productSlug' => $product->slug]);
@endphp

<article class="card p-3 group flex flex-col">

  <div class="plate aspect-square mb-3">
    {{-- The saving is a fact about this row, not a decoration, so it sits on
         the plate corner in the ink colour rather than in the brand colour the
         button uses. --}}
    @if ($onSale)
      <div class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-[var(--radius-sm)] bg-[var(--ink)] text-[var(--paper)] text-[11px] font-bold tracking-tight">
        −{{ $product->discountPercent() }}%
      </div>
    @endif

    {{-- Sold out is said in words, not left to a dimmed photograph to imply. --}}
    @if ($soldOut)
      <div class="absolute inset-0 bg-[var(--paper)]/50 z-10 flex items-center justify-center">
        <span class="px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] text-[var(--ink)] text-xs font-bold border border-[var(--rule)] shadow-sm">
          Out of stock
        </span>
      </div>
    @endif

    <div class="plate-media h-full">
      @if ($image)
        <img src="{{ \Illuminate\Support\Facades\Storage::url($image->path) }}"
             alt="{{ $product->name }}"
             width="400"
             height="400"
             loading="lazy"
             class="h-full w-full object-cover {{ $soldOut ? 'grayscale' : '' }}">
      @endif
    </div>

    <div class="grain" aria-hidden="true"></div>
  </div>

  <div class="flex flex-col flex-1">
    {{-- The category is the fastest way for a shopper to confirm they are in the
         right place before they read the name. --}}
    @if ($category)
      <p class="t-meta mb-1">{{ $category->name }}</p>
    @endif

    <div class="flex items-center justify-between gap-2">
      <h3 class="text-sm font-semibold text-[var(--ink)] line-clamp-1">
      <a href="{{ $href }}" class="card-stretched-link hover:underline">{{ $product->name }}</a>
      </h3>
      <x-themes.shared.verified-badge :page="$page" />
    </div>

    {{-- One line of evidence under the name: the rating when there is one, the
         sales figure when the ordering produced it, the stock when it is the
         thing worth knowing. --}}
    <div class="flex items-center gap-1.5 mt-1.5 mb-3 text-xs text-[var(--ink-3)]">
      @if ($product->hasRating())
        <span class="flex items-center text-[var(--rating)] font-bold"
              aria-label="{{ number_format($product->ratingLabel(), 1) }} stars out of 5">
          ★ {{ number_format($product->ratingLabel(), 1) }}
        </span>
        <span aria-hidden="true">·</span>
        <span>{{ $product->review_count }} {{ \Illuminate\Support\Str::plural('review', $product->review_count) }}</span>
      @elseif ($sold !== null && $sold > 0)
        <span>Sold {{ $sold }} {{ \Illuminate\Support\Str::plural('time', $sold) }}</span>
      @elseif ($product->track_stock && $soldOut)
        <span>Restocking soon</span>
      @endif
    </div>

    <div class="mt-auto pt-3 border-t border-[var(--rule)] flex items-center justify-between gap-3">
      <div class="flex items-baseline gap-2">
        <span class="sr-only">Price:</span>
        <p class="t-price-sm {{ $soldOut ? 'text-[var(--ink-3)]' : '' }}">{{ $product->priceLabel }}</p>

        {{-- The old price only appears when it is genuinely higher. A seller who
             typed them the wrong way round gets a plain price, not a negative
             discount. --}}
        @if ($onSale)
          <del class="text-xs text-[var(--ink-3)] tabular-nums">{{ \App\Shared\Support\Money::format($product->compare_at_kobo) }}</del>
        @endif
      </div>

      {{-- Real feedback, not decoration: the cart store writes to localStorage
           and this button is the only thing that tells a shopper their click
           landed. The spinner clears on the next frame, so a fast click does not
           leave it running after the label has already changed. --}}
      <button type="button"
              x-data="addToCartButton(@jsExpr(['id' => $product->id, 'name' => $product->name, 'priceKobo' => $product->price_kobo]))"
              @click="add()"
              @disabled($soldOut)
              class="relative z-20 px-3 py-1.5 text-xs font-semibold rounded-[var(--radius)] transition-opacity {{ $soldOut ? 'font-medium bg-[var(--surface)] text-[var(--ink-3)] border border-[var(--rule)] cursor-not-allowed' : 'bg-[var(--brand)] text-[var(--brand-ink)] hover:opacity-90' }}"
              aria-label="Add {{ $product->name }} to cart">
        <span x-show="adding" x-cloak aria-hidden="true"
              class="inline-block size-3 animate-spin rounded-full border-2 border-current border-t-transparent align-[-2px] ml-1.5 mr-1"></span>
        <span x-show="!$store.cart.items.some(i => i.id === {{ $product->id }})" x-transition.opacity.duration.150ms>
          Add to cart
        </span>
        <span x-show="$store.cart.items.some(i => i.id === {{ $product->id }})" x-cloak x-transition.opacity.duration.150ms>
          In cart
        </span>
      </button>
    </div>
  </div>

  {{-- Announced to a screen reader when the cart changes, which the visual
       "In cart" swap does not do on its own. --}}
  <span class="sr-only" aria-live="polite">
    <template x-if="$store.cart.items.some(i => i.id === {{ $product->id }})">{{ $product->name }} added to cart</template>
  </span>
</article>
