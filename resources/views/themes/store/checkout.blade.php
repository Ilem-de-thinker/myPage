@php
    /**
     * The cart lives in the browser, so this page is rendered by Alpine from
     * localStorage. Nothing is trusted: the summary below is for the buyer's
     * convenience only and every line is re-priced server side on submit.
     */
@endphp
<x-themes.shared.layout :page="$page">
  <section class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-6" style="font-family: var(--font-heading-stack)">Checkout</h1>

    <div x-data="{ submitting: false }">
      <template x-if="$store.cart.items.length === 0">
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200">
          <p class="text-sm text-slate-500 mb-4">Your cart is empty, so there is nothing to check out.</p>
          <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
             class="px-5 py-2.5 rounded-xl text-white font-semibold text-sm" style="background: var(--brand)">Browse products</a>
        </div>
      </template>

      <template x-if="$store.cart.items.length > 0">
        <form method="POST" action="{{ route('checkout.store', ['slug' => $page->slug]) }}"
              x-on:submit="submitting = true"
              class="space-y-5">
          @csrf

          @if ($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
              <ul class="text-xs text-red-700 space-y-1">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="text-[11px] uppercase tracking-wide text-slate-400 mb-3">Your details</h2>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 mb-1.5">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm"
                       placeholder="Your full name">
              </div>

              <div>
                <label for="phone" class="block text-xs font-semibold text-slate-700 mb-1.5">Phone</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm"
                       placeholder="0803 000 0000">
                <p class="text-[11px] text-slate-400 mt-1">So the seller can reach you about delivery.</p>
              </div>

              <div class="sm:col-span-2">
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">
                  Email <span class="text-red-500">*</span>
                </label>
                <input id="email" name="email" type="email" required
                       value="{{ old('email') }}"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm"
                       placeholder="you@example.com">
                <p class="text-[11px] text-slate-400 mt-1">Your receipt and payment confirmation go here.</p>
              </div>

              <div class="sm:col-span-2">
                <label for="note" class="block text-xs font-semibold text-slate-700 mb-1.5">Note for the seller</label>
                <textarea id="note" name="note" rows="3"
                          class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm"
                          placeholder="Delivery address, landmark, or anything else we should know">{{ old('note') }}</textarea>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <h2 class="text-[11px] uppercase tracking-wide text-slate-400 px-5 pt-5 pb-3">Order summary</h2>

            <template x-for="item in $store.cart.items" :key="item.id + ':' + (item.variant_id ?? '')">
              <div class="px-5 py-3 flex items-center justify-between gap-4 border-t border-slate-100">
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-ink">
                    <span x-text="item.qty + ' x '"></span><span x-text="item.name"></span>
                  </p>
                  <p class="text-xs text-slate-500" x-show="item.variant_name" x-text="'Option: ' + item.variant_name"></p>
                </div>
                <p class="text-sm font-semibold text-ink"
                   x-text="'₦' + ((item.price_kobo * item.qty) / 100).toLocaleString()"></p>
              </div>
            </template>

            <div class="px-5 py-4 flex items-center justify-between border-t border-slate-100 bg-slate-50">
              <span class="text-sm font-semibold text-slate-600">Total</span>
              <span class="text-lg font-extrabold" style="color: var(--brand)"
                    x-text="'₦' + ($store.cart.totalKobo / 100).toLocaleString()"></span>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('storefront.cart', ['slug' => $page->slug]) }}" class="text-xs font-semibold text-slate-500">
              &larr; Back to cart
            </a>

            <button type="submit" :disabled="submitting"
                    class="px-8 py-3.5 rounded-xl text-white font-semibold text-sm disabled:opacity-50"
                    style="background: var(--brand)">
              <span x-show="!submitting">Pay now</span>
              <span x-show="submitting">Taking you to payment…</span>
            </button>
          </div>

          <p class="text-[11px] text-slate-400 leading-relaxed">
            Prices are confirmed by {{ $page->name }} when your payment completes. You will be able to
            pay by card, bank transfer or USSD on the next screen.
          </p>

          {{-- Cart lines are posted as arrays; prices are never sent, the server re-prices them. --}}
          <template x-for="(item, index) in $store.cart.items" :key="'field-' + index">
            <div class="hidden">
              <input type="hidden" :name="'cart[' + index + '][product_id]'" :value="item.id">
              <input type="hidden" :name="'cart[' + index + '][variant_id]'" :value="item.variant_id ?? ''">
              <input type="hidden" :name="'cart[' + index + '][quantity]'" :value="item.qty">
            </div>
          </template>
        </form>
      </template>
    </div>
  </section>
</x-themes.shared.layout>