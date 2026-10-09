<x-themes.shared.layout :page="$page">
  <section class="max-w-2xl mx-auto px-4 py-12">
    @php $paid = $order->status === \App\Domains\Orders\Enums\OrderStatus::Paid; @endphp

    <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center">
      <div @class([
          'mx-auto w-14 h-14 rounded-full flex items-center justify-center text-2xl font-bold mb-5',
          'bg-emerald-100 text-emerald-700' => $paid,
          'bg-amber-100 text-amber-700' => ! $paid,
      ])>
        {{ $paid ? '✓' : '…' }}
      </div>

      <h1 class="text-2xl font-extrabold text-ink" style="font-family: var(--font-heading-stack)">
        @if ($paid)
          Thank you, your order is confirmed
        @else
          We are confirming your payment
        @endif
      </h1>

      <p class="text-sm text-slate-500 mt-2">
        Order reference <span class="font-bold text-ink">{{ $order->reference }}</span>
      </p>

      <p class="text-xs text-slate-400 mt-1">
        @if ($paid)
          {{ $page->name }} has your order and will be in touch about delivery.
        @else
          Card and bank payments can take a moment to clear. This page will not update on its own —
          refresh it in a minute, or check your email.
        @endif
      </p>

      <ul class="divide-y divide-slate-100 mt-6 text-left">
        @foreach ($order->items as $item)
          <li class="flex items-center justify-between gap-4 py-3">
            <div>
              <p class="text-sm font-semibold text-ink">{{ $item->quantity }} x {{ $item->name }}</p>
              @if ($item->sku)
                <p class="text-[11px] text-slate-400">{{ $item->sku }}</p>
              @endif
            </div>
            <p class="text-sm font-semibold text-ink">{{ \App\Shared\Support\Money::format($item->line_kobo) }}</p>
          </li>
        @endforeach

        <li class="flex items-center justify-between gap-4 pt-4">
          <span class="text-sm font-semibold text-slate-600">Total</span>
          <span class="text-lg font-extrabold" style="color: var(--brand)">
            {{ \App\Shared\Support\Money::format($order->total_kobo) }}
          </span>
        </li>
      </ul>

      <div class="flex flex-wrap justify-center gap-3 mt-7">
        <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
           class="px-6 py-3 rounded-xl border border-slate-300 font-semibold text-sm text-slate-700">Keep shopping</a>
        <a href="{{ route('storefront.show', ['slug' => $page->slug]) }}"
           class="px-6 py-3 rounded-xl text-white font-semibold text-sm" style="background: var(--brand)">Back to {{ $page->name }}</a>
      </div>
    </div>
  </section>
</x-themes.shared.layout>