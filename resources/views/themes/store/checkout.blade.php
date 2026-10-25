<x-themes.shared.layout :page="$page">
  {{--
    The cart lives in the browser, so this page is rendered by Alpine from
    localStorage. Nothing is trusted: the summary below is for the buyer's
    convenience only and every line is re-priced server side on submit.
  --}}
  <div class="shell pt-24 pb-16 md:pt-28">
    <div class="mb-8">
      <nav aria-label="Breadcrumb" class="mb-2">
        <ol class="flex items-center gap-2 text-xs text-[var(--ink-3)]">
          <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Home</a></li>
          <li aria-hidden="true">/</li>
          <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.cart', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Bag</a></li>
          <li aria-hidden="true">/</li>
          <li class="text-[var(--ink)] font-semibold" aria-current="page">Checkout</li>
        </ol>
      </nav>

      <h1 class="t-display-2 text-[var(--ink)]">Secure Checkout</h1>
      <p class="t-body text-xs text-[var(--ink-2)] mt-1">
        Buyers never need an account. Your payment goes straight to {{ $page->name }} through their own payment account.
      </p>
      @if (! $page->verified_at)
        <div class="mt-4 flex gap-3 rounded-[var(--radius)] border border-[var(--warn-edge)] bg-[var(--warn-wash)] p-4 text-xs text-[var(--warn-text)]" role="alert">
          <span class="font-bold" aria-hidden="true">!</span>
          <p><strong>{{ $page->name }} is not verified by mypage.ng.</strong> Your payment goes directly to this seller, not to mypage.ng, and the platform cannot vouch for the transaction.</p>
        </div>
      @endif
    </div>

    <div x-data="storeCheckout(@jsExpr(['captureUrl' => \App\Shared\Support\StoreUrl::path('storefront.checkout.capture', ['slug' => $page->slug]), 'csrf' => csrf_token()]))">

      <template x-if="$store.cart.items.length === 0">
        <div class="py-20 text-center">
          <p class="t-title">Your cart is empty, so there is nothing to check out.</p>
          <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', ['slug' => $page->slug]) }}"
             class="mt-6 inline-flex min-h-12 items-center justify-center rounded-[var(--radius)] px-7 text-[15px] font-bold"
             style="background: var(--brand); color: var(--brand-ink)">Shop the collection</a>
        </div>
      </template>

      <template x-if="$store.cart.items.length > 0">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

          {{-- Left column: what the platform has to know to deliver an order. --}}
          <div class="lg:col-span-7">
            <form method="POST" action="{{ route('checkout.store', ['slug' => $page->slug]) }}"
                  x-on:submit="submitting = true"
                  class="space-y-6">
              @csrf

              @if ($errors->any())
                <div role="alert" class="rounded-[var(--radius)] border border-[var(--danger-edge)] bg-[var(--danger-wash)] p-4">
                  <ul class="t-body space-y-1" style="color: var(--danger-text)">
                    @foreach ($errors->all() as $error)
                      <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              <div class="card p-6 bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] space-y-4">
                <h2 class="text-sm font-bold text-[var(--ink)] pb-3 border-b border-[var(--rule)]">
                  1. Customer &amp; WhatsApp Notification
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label for="name" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                      Recipient Full Name <span class="text-[var(--danger-text)]" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name') }}"
                           required
                           class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                  </div>

                  <div>
                    <label for="phone" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                      WhatsApp Phone Number <span class="text-[var(--danger-text)]" aria-hidden="true">*</span>
                    </label>
                    <div class="relative flex items-center">
                      <span class="absolute left-3 text-xs font-bold text-[var(--ink-3)] select-none">+234</span>
                      <input type="tel"
                             id="phone"
                             name="phone"
                             value="{{ old('phone') }}"
                             required
                             x-on:blur="capture()"
                             class="w-full h-11 pl-14 pr-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] font-mono tabular-nums">
                    </div>
                    <p class="t-meta mt-1">Order updates are sent to this WhatsApp number.</p>
                  </div>
                </div>

                <div>
                  <label for="email" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                    Email Address <span class="text-[var(--ink-3)] font-normal">(Your receipt)</span>
                  </label>
                  <input type="email"
                         id="email"
                         name="email"
                         value="{{ old('email') }}"
                         x-on:blur="capture()"
                         class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                </div>
              </div>

              <div class="card p-6 bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] space-y-4">
                <h2 class="text-sm font-bold text-[var(--ink)] pb-3 border-b border-[var(--rule)]">
                  2. Delivery Destination
                </h2>

                <div>
                  <label for="note" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                    Delivery Address, Landmark &amp; Dispatch Instructions
                  </label>
                  <textarea id="note"
                            name="note"
                            rows="3"
                            placeholder="Street address, house or apartment number, and the nearest landmark"
                            class="w-full p-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">{{ old('note') }}</textarea>
                  <p class="t-meta mt-1">
                    {{ $page->name }} will confirm the address with you on WhatsApp before dispatch.
                  </p>
                </div>
              </div>

              <button type="submit" :disabled="submitting"
                      class="w-full h-14 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 shadow-sm transition-opacity disabled:opacity-60">
                <span x-show="!submitting">Pay now</span>
                <span x-show="submitting" x-cloak>Taking you to payment…</span>
              </button>

              {{-- Cart lines are posted as arrays; prices are never sent, the server re-prices them. --}}
              <template x-for="(item, index) in $store.cart.items" :key="'field-' + index">
                <div class="hidden">
                  <input type="hidden" :name="'cart[' + index + '][product_id]'" :value="item.id">
                  <input type="hidden" :name="'cart[' + index + '][variant_id]'" :value="item.variant_id ?? ''">
                  <input type="hidden" :name="'cart[' + index + '][quantity]'" :value="item.qty">
                </div>
              </template>
            </form>
          </div>

          {{-- Right column: the summary, sticky beside the form so the total is
               never more than a glance away from the button that spends it. --}}
          <div class="lg:col-span-5">
            <div class="lg:sticky lg:top-24 bg-[var(--card)] p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
              <div class="pb-4 border-b border-[var(--rule)] mb-4 flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--ink)]">Order Summary</h2>
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.cart', ['slug' => $page->slug]) }}" class="text-xs text-[var(--brand-text)] font-bold hover:underline">
                  Edit bag
                </a>
              </div>

              <div class="divide-y divide-[var(--rule)] mb-4">
                <template x-for="item in $store.cart.items" :key="item.id + ':' + (item.variant_id ?? '')">
                  <div class="py-3 flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3 min-w-0">
                      <div>
                        <p class="font-bold text-[var(--ink)] truncate" x-text="item.name"></p>
                        <p class="t-meta" x-text="(item.variant_name ? item.variant_name + ' · ' : '') + 'Qty: ' + item.qty"></p>
                      </div>
                    </div>
                    <span class="font-bold text-[var(--ink)] tabular-nums shrink-0"
                          x-text="'₦' + ((item.price_kobo * item.qty) / 100).toLocaleString()"></span>
                  </div>
                </template>
              </div>

              <div class="space-y-2 pt-3 border-t border-[var(--rule)] text-xs">
                <div class="flex items-center justify-between">
                  <span class="text-[var(--ink-2)]">Subtotal</span>
                  <span class="font-medium text-[var(--ink)] tabular-nums" x-text="'₦' + ($store.cart.totalKobo / 100).toLocaleString()"></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-[var(--ink-2)]">Processing Fee</span>
                  <span class="font-bold text-[var(--brand-text)]">₦0 (Included)</span>
                </div>
                <div class="flex items-center justify-between pt-3 border-t border-[var(--rule)]">
                  <span class="text-sm font-bold text-[var(--ink)]">Total Amount</span>
                  <span class="t-price-lg text-[var(--ink)]" x-text="'₦' + ($store.cart.totalKobo / 100).toLocaleString()"></span>
                </div>
              </div>

              <div class="mt-6 p-4 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] text-xs text-[var(--brand-text)]">
                <div class="flex items-center gap-2 font-bold mb-1">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  <span>Paystack / Flutterwave</span>
                </div>
                <p class="text-[11px] leading-relaxed text-[var(--ink-2)]">
                  Payment is collected by {{ $page->name }}'s own gateway account. mypage.ng does not receive or hold your money.
                </p>
              </div>

              <p class="text-[11px] text-[var(--ink-3)] leading-relaxed mt-4">
                Prices are confirmed by {{ $page->name }} when your payment completes. You will be able to
                pay through the seller's gateway on the next screen.
              </p>
            </div>
          </div>

        </div>
      </template>
    </div>
  </div>
</x-themes.shared.layout>
