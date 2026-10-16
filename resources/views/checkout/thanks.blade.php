<x-themes.shared.layout :page="$page">
  <section class="max-w-2xl mx-auto px-4 py-12">
    @php $paid = $order->status === \App\Domains\Orders\Enums\OrderStatus::Paid; @endphp

    {{--
        Colours come from the seller's token set, never from the platform's own
        greys. `bg-white` plus `text-ink` looked right on a light page and left
        the whole receipt white on white the moment the buyer switched to dark:
        the card kept its hardcoded white while the ink flipped to near-white.
    --}}
    <div class="card bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] p-8 text-center">
      <div @class([
          'mx-auto w-14 h-14 rounded-full flex items-center justify-center text-2xl font-bold mb-5',
          'bg-[var(--brand-wash)] text-[var(--brand-text)]' => $paid,
          'bg-[var(--surface)] text-[var(--ink-2)]' => ! $paid,
      ])>
        {{ $paid ? '✓' : '…' }}
      </div>

      <h1 class="text-2xl font-extrabold text-[var(--ink)]" style="font-family: var(--font-heading-stack)">
        @if ($paid)
          Thank you, your order is confirmed
        @else
          We are confirming your payment
        @endif
      </h1>

      <p class="text-sm text-[var(--ink-2)] mt-2">
        Order reference <span class="font-bold text-[var(--ink)]">{{ $order->reference }}</span>
      </p>

      <p class="text-xs text-[var(--ink-3)] mt-1">
        @if ($paid)
          {{ $page->name }} has your order and will be in touch about delivery.
        @else
          Card and bank payments can take a moment to clear. This page will not update on its own —
          refresh it in a minute, or check your email.
        @endif
      </p>

      <ul class="divide-y divide-[var(--rule)] mt-6 text-left">
        @foreach ($order->items as $item)
          <li class="flex items-center justify-between gap-4 py-3">
            <div>
              <p class="text-sm font-semibold text-[var(--ink)]">{{ $item->quantity }} x {{ $item->name }}</p>
              @if ($item->sku)
                <p class="text-[11px] text-[var(--ink-3)]">{{ $item->sku }}</p>
              @endif
            </div>
            <p class="text-sm font-semibold text-[var(--ink)] tabular-nums">{{ \App\Shared\Support\Money::format($item->line_kobo) }}</p>
          </li>
        @endforeach

        <li class="flex items-center justify-between gap-4 pt-4">
          <span class="text-sm font-semibold text-[var(--ink-2)]">Total</span>
          <span class="text-lg font-extrabold tabular-nums text-[var(--brand-text)]">
            {{ \App\Shared\Support\Money::format($order->total_kobo) }}
          </span>
        </li>
      </ul>

      {{--
          The link the buyer keeps.

          This is the one screen where an account would be worst: they have just
          paid, they are on a phone, and asking them to register before they can
          see their own order is the exact friction this platform promises not to
          add. The link is signed, so it needs nothing from them to work.
      --}}
      @php $trackLink = \App\Shared\Support\BuyerLinks::order($page, $order); @endphp
      <div class="mt-7 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] p-5 text-left">
        <p class="t-meta">Save this link</p>
        <p class="t-body text-xs text-[var(--ink-2)] mt-1">
          It is how you check on this order without making an account, and it works
          for {{ \App\Shared\Support\BuyerLinks::EXPIRES_DAYS }} days.
        </p>
        <p class="font-mono text-[11px] text-[var(--ink-3)] mt-2 break-all">{{ $trackLink }}</p>
        <a href="{{ $trackLink }}"
           class="relative z-20 mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold hover:opacity-90 transition-opacity">
          <span>Open my order</span>
          <span aria-hidden="true">→</span>
        </a>
      </div>

      <div class="flex flex-wrap justify-center gap-3 mt-7">
        <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
           class="px-6 py-3 rounded-[var(--radius)] bg-[var(--card)] border border-[var(--rule)] text-[var(--ink)] font-semibold text-sm hover:border-[var(--brand-edge)] transition-colors">Keep shopping</a>
        <a href="{{ route('storefront.show', ['slug' => $page->slug]) }}"
           class="px-6 py-3 rounded-[var(--radius)] text-[var(--brand-ink)] font-semibold text-sm" style="background: var(--brand)">Back to {{ $page->name }}</a>
      </div>
    </div>
  </section>
</x-themes.shared.layout>