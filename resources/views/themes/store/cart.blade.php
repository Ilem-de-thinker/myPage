{{--
    The cart lives in the browser, so nothing on this page is trusted: it is
    rendered by Alpine from localStorage and every line is re-priced server side
    the moment the buyer checks out or asks for a WhatsApp order.
--}}
<x-themes.shared.layout :page="$page">
  <div x-data="{ busy: false, error: '', reference: '' }">
    <div class="shell pt-24 pb-16 md:pt-28">
      <div class="max-w-3xl mx-auto">

        <div class="mb-8">
          <nav aria-label="Breadcrumb" class="mb-2">
            <ol class="flex items-center gap-2 text-xs text-[var(--ink-3)]">
              <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Home</a></li>
              <li aria-hidden="true">/</li>
              <li class="text-[var(--ink)] font-semibold" aria-current="page">Shopping Bag</li>
            </ol>
          </nav>

          <span class="t-meta block mb-1">{{ $page->name }}{{ $page->locationLabel() ? ' · '.$page->locationLabel() : '' }}</span>
          <h1 class="t-display-2 text-[var(--ink)]">Order Bag &amp; Checkout</h1>
          <p class="t-body text-xs text-[var(--ink-2)] mt-1">
            Review your items below. Complete checkout online, or finish the order on WhatsApp.
          </p>
        </div>

        {{--
            The receipt card. Rows are separated by hairlines rather than by boxes,
            because that is what a receipt is: a list of what you are about to pay
            for, with the running total where a receipt puts it.
        --}}
        <div class="card p-6 sm:p-8 bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">

          <div class="flex items-center justify-between pb-4 border-b border-[var(--rule)] mb-6 gap-4">
            <div>
              <span class="text-xs font-mono font-bold text-[var(--ink-3)]" x-show="reference" x-text="reference ? 'REFERENCE: ' + reference : ''" x-cloak></span>
              <p class="text-xs text-[var(--ink)] font-semibold mt-0.5">
                <span x-text="$store.cart.count"></span>
                <span x-text="$store.cart.count === 1 ? 'Item in your bag' : 'Items in your bag'">Items in your bag</span>
              </p>
            </div>

            <span class="px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold shrink-0">
              Secure Checkout
            </span>
          </div>

          <template x-if="$store.cart.items.length === 0">
            <div class="py-16 text-center">
              <p class="t-title">Your cart is empty.</p>
              <p class="t-body mt-2 text-[color:var(--ink-2)]">Nothing here yet.</p>
              <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
                 class="mt-6 inline-flex min-h-12 items-center justify-center rounded-[var(--radius)] px-7 text-[15px] font-bold"
                 style="background: var(--brand); color: var(--brand-ink)">
                Shop the collection
              </a>
            </div>
          </template>

          <template x-if="$store.cart.items.length > 0">
            <div>
              <div class="divide-y divide-[var(--rule)]">
                <template x-for="item in $store.cart.items" :key="item.id + ':' + (item.variant_id ?? '')">
                  <div class="py-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="min-w-0">
                      <h3 class="text-sm font-bold text-[var(--ink)]" x-text="item.name"></h3>
                      <p class="t-meta mt-0.5" x-show="item.variant_name" x-text="item.variant_name"></p>
                      <span class="text-xs font-bold text-[var(--ink-2)] tabular-nums mt-1 block"
                            x-text="'₦' + (item.price_kobo / 100).toLocaleString() + ' each'"></span>
                    </div>

                    <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                      <div class="inline-flex items-center rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)]">
                        <button type="button"
                                @click="$store.cart.setQty(item.id, item.variant_id, item.qty - 1)"
                                :disabled="item.qty <= 1"
                                aria-label="Decrease quantity"
                                class="w-11 min-h-11 flex items-center justify-center text-[var(--ink)] hover:bg-[var(--surface)] font-bold text-sm disabled:opacity-40">
                          −
                        </button>
                        <span class="w-11 min-h-11 flex items-center justify-center text-xs font-bold text-[var(--ink)] border-x border-[var(--rule)] tabular-nums" x-text="item.qty"></span>
                        <button type="button"
                                @click="$store.cart.setQty(item.id, item.variant_id, item.qty + 1)"
                                aria-label="Increase quantity"
                                class="w-11 min-h-11 flex items-center justify-center text-[var(--ink)] hover:bg-[var(--surface)] font-bold text-sm">
                          +
                        </button>
                      </div>

                      <span class="font-bold text-sm text-[var(--ink)] tabular-nums min-w-20 text-right"
                            x-text="'₦' + ((item.price_kobo * item.qty) / 100).toLocaleString()"></span>

                      <button type="button"
                              @click="$store.cart.remove(item.id, item.variant_id)"
                              class="text-xs font-semibold underline underline-offset-4 shrink-0"
                              style="color: var(--danger-text)">
                        Remove
                      </button>
                    </div>
                  </div>
                </template>
              </div>

              {{-- The receipt stack. Delivery is quoted by the seller per order,
                   so the only line between the subtotal and the total is the one
                   the platform can actually promise. --}}
              <div class="space-y-2.5 pt-4 border-t border-[var(--rule)] text-xs">
                <div class="flex items-center justify-between">
                  <span class="text-[var(--ink-2)]">Items Subtotal</span>
                  <span class="font-medium text-[var(--ink)] tabular-nums" x-text="'₦' + ($store.cart.totalKobo / 100).toLocaleString()"></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-[var(--ink-2)]">Checkout Fee</span>
                  <span class="font-bold text-[var(--brand-text)]">₦0</span>
                </div>
                <div class="flex items-center justify-between pt-4 border-t border-[var(--rule)]">
                  <span class="text-sm font-bold text-[var(--ink)]">Total Payable</span>
                  <span class="t-price-lg text-[var(--ink)]" x-text="'₦' + ($store.cart.totalKobo / 100).toLocaleString()"></span>
                </div>
              </div>

              <p x-show="error" x-cloak x-text="error" role="alert" class="t-body mt-4" style="color: var(--danger-text)"></p>

              <div class="mt-8 pt-6 border-t border-[var(--rule)] space-y-3">
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.checkout', ['slug' => $page->slug]) }}"
                   class="w-full h-12 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity">
                  <span>Proceed to Checkout →</span>
                </a>

                <button type="button" :disabled="busy"
                        @click="busy = true; error = ''; reference = '';
                            fetch('{{ \App\Shared\Support\StoreUrl::path('storefront.checkout.whatsapp', ['slug' => $page->slug]) }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify($store.cart.payload())
                            }).then(async r => {
                                if (!r.ok) { const e = await r.json(); error = e.message ?? 'Could not build your order'; busy = false; return; }
                                const data = await r.json();
                                reference = data.reference;
                                window.open(data.url, '_blank');
                                busy = false;
                            }).catch(() => { error = 'Network error'; busy = false; })"
                        class="w-full h-12 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-95 transition-opacity disabled:opacity-60">
                  <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.98-.276-.101-.477-.15-.678.15-.2.302-.779.98-.955 1.18-.176.202-.352.226-.653.076-.301-.15-1.272-.469-2.424-1.496-.896-.799-1.501-1.787-1.677-2.088-.176-.302-.019-.465.132-.615.136-.135.301-.352.452-.528.15-.175.2-.299.301-.502.101-.2.05-.376-.025-.526-.075-.15-.678-1.634-.93-2.238-.243-.589-.49-.509-.678-.519-.176-.01-.377-.01-.578-.01s-.527.075-.803.376c-.276.301-1.054 1.03-1.054 2.512s1.08 2.914 1.23 3.115c.15.201 2.126 3.245 5.15 4.553.72.311 1.282.497 1.72.636.723.23 1.381.197 1.901.12.579-.087 1.78-.728 2.032-1.431.251-.703.251-1.306.176-1.431-.076-.126-.277-.202-.578-.352z"/></svg>
                  <span x-show="!busy">Order via WhatsApp Direct</span>
                  <span x-show="busy" x-cloak>Preparing your order…</span>
                </button>

                <div class="text-center pt-2">
                  <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}" class="text-xs text-[var(--ink-3)] hover:text-[var(--ink)]">
                    ← Continue shopping
                  </a>
                </div>
              </div>

              <div class="mt-6 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] text-xs text-[var(--ink-2)] flex items-start gap-3">
                <svg class="w-5 h-5 text-[var(--brand)] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                <div>
                  <p class="font-bold text-[var(--ink)]">Secure Payment</p>
                  <p class="mt-0.5 leading-relaxed text-[var(--ink-3)]">
                    Your payment goes straight to {{ $page->name }} through their own payment account. mypage.ng never holds it.
                  </p>
                </div>
              </div>
            </div>
          </template>
        </div>

      </div>
    </div>
  </div>
</x-themes.shared.layout>
