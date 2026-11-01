<x-themes.shared.layout :page="$page">
  @php
      // No `use` in here: this block sits inside the layout component's slot, and
      // a `use` statement is only legal at the outermost scope of a file.
      $images = $product->images;
      $variants = $product->variants->sortBy('name')->values();
      $options = $variants->map(fn ($variant) => [
          'id' => $variant->id,
          'name' => $variant->name,
          'price_kobo' => $variant->priceKobo(),
          'stock' => $variant->stock_qty,
      ])->all();
      $gallery = $images->map(fn ($image) => [
          'url' => $image->url(),
          'thumbnail' => $image->thumbnailUrl(),
          'alt' => $image->alt ?: $product->name,
      ])->values()->all();
      $whatsapp = filled($page->whatsapp_number)
          ? \App\Shared\Support\WhatsApp::productLink($page, $product)
          : null;
      $soldOut = $product->isSoldOut();
      // Variants carry their own prices, so a base-product discount would not
      // be the number the buyer is about to pay.
      $showSale = $product->isOnSale() && $variants->isEmpty();
      $themePreset = $page->settings['theme_preset'] ?? 'catalog';
  @endphp

  <div x-data="storeProduct(@jsExpr([
        'tab' => $errors->any() || session('review_sent') ? 'reviews' : 'details',
        'variantId' => $variants->isEmpty() ? null : $options[0]['id'],
        'options' => $options,
        'gallery' => $gallery,
        'productId' => $product->id,
        'productName' => $product->name,
        'priceKobo' => $product->price_kobo,
        'maxQty' => $product->track_stock ? $product->stock_qty : 99,
    ]))">

    <article data-detail-style="{{ $themePreset }}" class="theme-detail theme-detail-store shell pt-24 pb-16 md:pt-28">
      <nav aria-label="Breadcrumb" class="mb-6">
        <ol class="flex items-center gap-2 text-xs text-[var(--ink-3)]">
          <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Home</a></li>
          <li aria-hidden="true">/</li>
          <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Catalogue</a></li>
          <li aria-hidden="true">/</li>
          <li class="text-[var(--ink)] font-semibold truncate max-w-xs" aria-current="page">{{ $product->name }}</li>
        </ol>
      </nav>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-10 items-start">

        {{-- Left column: the gallery and the seller's own account of the piece. --}}
        <div class="md:col-span-7 space-y-6">

          <div class="plate aspect-[4/3] rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
            <div class="plate-media h-full">
              @if ($images->isNotEmpty())
                  <img src="{{ $images->first()->url() }}"
                     alt="{{ $product->images->first()->alt ?: $product->name }}"
                     x-bind:src="imageUrl"
                     x-bind:alt="imageAlt"
                     width="800" height="600"
                     class="w-full h-full object-cover"
                     fetchpriority="high" decoding="async">
              @endif
            </div>
            <div class="grain" aria-hidden="true"></div>
          </div>

          {{-- One thumbnail per photograph the seller uploaded, and no more. --}}
          @if ($images->count() > 1)
            <div class="grid grid-cols-4 gap-3" aria-label="Product images">
              <template x-for="(image, index) in gallery" :key="image.url">
                <button type="button"
                        @click="activeImage = index"
                        :aria-label="'View image ' + (index + 1)"
                        :aria-current="activeImage === index ? 'true' : 'false'"
                        :class="activeImage === index ? 'border-[var(--brand)] ring-2 ring-[var(--brand)]' : 'border-[var(--rule)]'"
                        class="plate aspect-square rounded-[var(--radius)] border overflow-hidden focus-visible:outline-none">
                  <img x-bind:src="image.thumbnail"
                       x-bind:alt="image.alt"
                       width="200" height="200" loading="lazy"
                       class="w-full h-full object-cover">
                </button>
              </template>
            </div>
          @endif

          <div class="pt-6 border-t border-[var(--rule)]">
            <div class="flex items-center gap-4 border-b border-[var(--rule)] mb-4 text-xs font-bold" role="tablist">
              <button type="button"
                      @click="tab = 'details'"
                      :class="tab === 'details' ? 'border-b-2 border-[var(--brand)] text-[var(--brand-text)] pb-2' : 'text-[var(--ink-3)] hover:text-[var(--ink)] pb-2'">
                Product Details
              </button>
              <button type="button"
                      @click="tab = 'reviews'"
                      :class="tab === 'reviews' ? 'border-b-2 border-[var(--brand)] text-[var(--brand-text)] pb-2' : 'text-[var(--ink-3)] hover:text-[var(--ink)] pb-2'">
                Customer Reviews ({{ $product->review_count }})
              </button>
            </div>

            <div x-show="tab === 'details'" class="space-y-3 text-xs text-[var(--ink-2)] leading-relaxed">
              @if ($product->description)
                <div class="t-body t-measure-narrow [&_a]:underline [&_a]:underline-offset-4 [&_li]:ml-5 [&_p]:mt-3">
                  {!! \App\Shared\Support\Html::sanitize($product->description) !!}
                </div>
              @else
                <p>{{ $page->name }} has not written a description for this piece yet.</p>
              @endif

              <ul class="space-y-1.5 list-disc pl-4 pt-1">
                @if ($product->sku)
                  <li><strong class="text-[var(--ink)]">SKU:</strong> {{ $product->sku }}</li>
                @endif

                @if ($product->category)
                  <li><strong class="text-[var(--ink)]">Category:</strong> {{ $product->category->name }}</li>
                @endif

                @if ($product->track_stock)
                  <li>
                    <strong class="text-[var(--ink)]">Availability:</strong>
                    {{ $soldOut ? 'Out of stock' : $product->stock_qty.' in stock' }}
                  </li>
                @endif
              </ul>
            </div>

            {{--
                Reviews.

                The list is built from approved rows only — Review::published() in
                the controller's eager load — and the form is a plain POST that
                lands as pending. Nothing a buyer writes reaches this page unread.
            --}}
            <div x-show="tab === 'reviews'" style="display: none;">
              <x-ui.review-list
                  :framed="false"
                  :level="3"
                  :reviews="$product->reviews"
                  :rating="$product->rating"
                  :count="$product->review_count"
                  heading="What buyers say" />

              <div class="mt-6">
                <x-ui.review-form
                    :action="\App\Shared\Support\StoreUrl::path('storefront.product.review', ['slug' => $page->slug, 'productSlug' => $product->slug])"
                    :reviewable="$product"
                    :subject="$product->name" />
              </div>
            </div>
          </div>
        </div>

        {{-- Right column: one contiguous purchase module, sticky beside the
             photograph so the price and the button never scroll away from it. --}}
        <div class="md:col-span-5 sticky top-24 bg-[var(--card)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">

          <div class="pb-4 border-b border-[var(--rule)] mb-6">
            @if ($product->category)
              <span class="t-meta block mb-1">{{ $product->category->name }}</span>
            @endif

            <div class="flex flex-wrap items-center gap-2">
              <h1 class="t-title text-xl font-bold text-[var(--ink)]">{{ $product->name }}</h1>
              @if ($showSale)
                <span class="rounded-full bg-[var(--ink)] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--paper)]">
                  Sale −{{ $product->discountPercent() }}%
                </span>
              @endif
            </div>

            <div class="flex items-baseline gap-3 mt-3">
              <span class="t-price-lg text-[var(--brand-text)]" x-text="priceLabel">{{ $product->priceLabel }}</span>

              {{-- The struck price only when it is genuinely higher and live. --}}
              @if ($showSale)
                <del class="text-xs text-[var(--ink-3)] tabular-nums">{{ \App\Shared\Support\Money::format($product->originalPriceKobo()) }}</del>
              @endif
            </div>

            @if ($showSale && $product->saleEndsLabel())
              <p class="mt-1 text-[11px] font-semibold text-[var(--ink-3)]">{{ $product->saleEndsLabel() }}</p>
            @endif

            <div class="flex items-center gap-2 mt-2 text-xs text-[var(--ink-3)]">
              @if ($product->hasRating())
                <span class="font-bold text-[var(--rating)]" aria-label="{{ number_format($product->ratingLabel(), 1) }} out of 5">★★★★★ {{ number_format($product->ratingLabel(), 1) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $product->review_count }} verified {{ \Illuminate\Support\Str::plural('review', $product->review_count) }}</span>
              @else
                <span>No reviews yet</span>
              @endif
            </div>
          </div>

          <div class="space-y-6">

            {{-- The variant list. The price above follows the chosen one, so a
                 buyer never has to add one price to another and guess. --}}
            @if ($variants->isNotEmpty())
              <fieldset>
                <legend class="text-xs font-bold text-[var(--ink)] mb-2">Options</legend>
                <div class="space-y-2">
                  <template x-for="option in options" :key="option.id">
                    <label :class="variantId === option.id ? 'border-[var(--brand)] ring-1 ring-[var(--brand)] bg-[var(--brand-wash)]' : 'border-[var(--rule)] bg-[var(--paper)]'"
                           class="flex items-center justify-between gap-3 p-3 rounded-[var(--radius)] border cursor-pointer text-xs transition-colors">
                      <span class="flex items-center gap-2.5 min-w-0">
                        <input type="radio" name="variant" :value="option.id" x-model="variantId"
                               class="text-[var(--brand)] accent-[var(--brand)]">
                        <span class="font-bold text-[var(--ink)] truncate" x-text="option.name"></span>
                      </span>
                      <span class="font-bold text-[var(--ink)] tabular-nums shrink-0"
                            x-text="option.stock < 1 ? 'Sold out' : '₦' + ((option.price_kobo || 0) / 100).toLocaleString()"></span>
                    </label>
                  </template>
                </div>
                <p class="t-meta mt-2"><span x-text="maxQty"></span> available for this option</p>
              </fieldset>
            @endif

            <div>
              <label for="detail-qty" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                Quantity
              </label>
              <div class="inline-flex items-center rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] overflow-hidden">
                <button type="button"
                        @click="decrementQty()"
                        :disabled="qty <= 1"
                        aria-label="Decrease quantity"
                        class="w-12 h-12 flex items-center justify-center text-[var(--ink)] hover:bg-[var(--surface)] text-base font-bold disabled:opacity-40">
                  −
                </button>
                <input type="text"
                       id="detail-qty"
                       readonly
                       :value="qty"
                       class="w-12 h-12 text-center text-sm font-bold text-[var(--ink)] bg-transparent border-x border-[var(--rule)] tabular-nums"
                       aria-live="polite">
                <button type="button"
                        @click="incrementQty()"
                        aria-label="Increase quantity"
                        class="w-12 h-12 flex items-center justify-center text-[var(--ink)] hover:bg-[var(--surface)] text-base font-bold">
                  +
                </button>
              </div>
            </div>

            <div class="space-y-3 pt-2">
              <button type="button"
                      @click="addToCart()"
                      :disabled="maxQty < 1"
                      class="w-full h-12 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity disabled:opacity-40 disabled:cursor-not-allowed">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span x-text="inCart() ? 'Add more to bag' : 'Add to Shopping Bag'">Add to Shopping Bag</span>
              </button>

              {{-- Announced to a screen reader when the bag changes, which the
                   button's own label swap does not do on its own. --}}
              <p class="t-meta text-center" x-show="$store.cart.count > 0" x-cloak aria-live="polite"
                 x-text="$store.cart.count + ' ' + ($store.cart.count === 1 ? 'item' : 'items') + ' in your cart'"></p>

              <div class="grid grid-cols-2 gap-3">
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.cart', ['slug' => $page->slug]) }}"
                   class="h-11 rounded-[var(--radius)] bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] font-bold text-xs flex items-center justify-center hover:bg-[var(--paper)] transition-colors">
                  Go to Bag
                </a>

                @if ($whatsapp)
                  <a href="{{ $whatsapp }}"
                     target="_blank"
                     rel="noopener noreferrer"
                     class="h-11 rounded-[var(--radius)] bg-transparent text-[var(--brand-text)] border border-[var(--brand-edge)] font-bold text-xs flex items-center justify-center gap-1.5 hover:bg-[var(--brand-wash)] transition-colors">
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.98-.276-.101-.477-.15-.678.15-.2.302-.779.98-.955 1.18-.176.202-.352.226-.653.076-.301-.15-1.272-.469-2.424-1.496-.896-.799-1.501-1.787-1.677-2.088-.176-.302-.019-.465.132-.615.136-.135.301-.352.452-.528.15-.175.2-.299.301-.502.101-.2.05-.376-.025-.526-.075-.15-.678-1.634-.93-2.238-.243-.589-.49-.509-.678-.519-.176-.01-.377-.01-.578-.01s-.527.075-.803.376c-.276.301-1.054 1.03-1.054 2.512s1.08 2.914 1.23 3.115c.15.201 2.126 3.245 5.15 4.553.72.311 1.282.497 1.72.636.723.23 1.381.197 1.901.12.579-.087 1.78-.728 2.032-1.431.251-.703.251-1.306.176-1.431-.076-.126-.277-.202-.578-.352z"/></svg>
                    <span>Inquire on WhatsApp</span>
                  </a>
                @endif
              </div>
            </div>

            <div class="pt-4 border-t border-[var(--rule)] space-y-2 text-xs text-[var(--ink-3)]">
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[var(--brand)] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Tracked delivery, with a waybill reference on every order</span>
              </div>
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-[var(--brand)] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Secure online payment, straight to the seller</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      @if ($related->isNotEmpty())
        <section class="section-y border-t border-[var(--rule)] mt-16">
          <div class="flex items-center justify-between mb-8 gap-4">
            <div>
              <p class="t-meta">Complete the Look</p>
              <h2 class="t-display-2 text-[var(--ink)] mt-1">You may also like</h2>
            </div>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
               class="text-xs font-bold text-[var(--brand-text)] hover:underline shrink-0">
              View the catalogue →
            </a>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($related as $item)
              @include('themes.store.partials.product-card', ['product' => $item, 'page' => $page])
            @endforeach
          </div>
        </section>
      @endif
    </article>
  </div>
</x-themes.shared.layout>
