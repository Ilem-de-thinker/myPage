<x-themes.shared.layout :page="$page">
  <section class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-6" style="font-family: var(--font-heading-stack)">Your cart</h1>

    <div x-data="{ busy: false, error: '', reference: '' }">
      <template x-if="$store.cart.items.length === 0">
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200">
          <p class="text-sm text-slate-500 mb-4">Your cart is empty.</p>
          <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}" class="px-5 py-2.5 rounded-xl text-white font-semibold text-sm" style="background: var(--brand)">Browse products</a>
        </div>
      </template>

      <template x-if="$store.cart.items.length > 0">
        <div>
          <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            <template x-for="item in $store.cart.items" :key="item.id + ':' + (item.variant_id ?? '')">
              <div class="p-4 flex items-center gap-4">
                <div class="min-w-0 flex-1">
                  <p class="font-semibold text-sm text-ink truncate" x-text="item.name"></p>
                  <p class="text-xs text-slate-500" x-show="item.variant_name" x-text="'Option: ' + item.variant_name"></p>
                  <p class="text-xs text-slate-500" x-text="'₦' + (item.price_kobo / 100).toLocaleString()"></p>
                </div>
                <div class="flex items-center gap-2">
                  <button type="button" @click="$store.cart.setQty(item.id, item.variant_id, item.qty - 1)" class="w-8 h-8 rounded-lg border border-slate-300 text-slate-600">−</button>
                  <span class="w-6 text-center text-sm" x-text="item.qty"></span>
                  <button type="button" @click="$store.cart.setQty(item.id, item.variant_id, item.qty + 1)" class="w-8 h-8 rounded-lg border border-slate-300 text-slate-600">+</button>
                </div>
                <p class="w-24 text-right text-sm font-semibold" x-text="'₦' + ((item.price_kobo * item.qty) / 100).toLocaleString()"></p>
                <button type="button" @click="$store.cart.remove(item.id, item.variant_id)" class="text-xs font-semibold text-red-500">Remove</button>
              </div>
            </template>
          </div>

          <div class="flex items-center justify-between mt-4 text-sm">
            <button type="button" @click="$store.cart.clear()" class="text-xs font-semibold text-slate-500">Clear cart</button>
            <p class="font-bold text-base">Total: <span x-text="'₦' + ($store.cart.totalKobo / 100).toLocaleString()"></span></p>
          </div>

          <p x-show="error" x-text="error" class="text-red-600 text-xs mt-3"></p>

          <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('storefront.checkout', ['slug' => $page->slug]) }}" class="px-6 py-3 rounded-xl border border-slate-300 font-semibold text-sm text-slate-700">Checkout</a>
            <button type="button" :disabled="busy"
                    @click="busy = true; error = ''; reference = '';
                        fetch('{{ route('storefront.checkout.whatsapp', ['slug' => $page->slug]) }}', {
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
                    class="px-6 py-3 rounded-xl text-white font-semibold text-sm" style="background: var(--brand)">
                <span x-show="!busy">Order on WhatsApp</span>
                <span x-show="busy">Preparing…</span>
            </button>
          </div>

          <p x-show="reference" class="text-xs text-emerald-700 mt-3">Order reference: <span class="font-bold" x-text="reference"></span> — share it if you need help with this order.</p>
        </div>
      </template>
    </div>
  </section>
</x-themes.shared.layout>