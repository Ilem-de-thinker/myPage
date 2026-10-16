@php
    use App\Shared\Support\Money;
    use App\Shared\Support\WhatsApp;
@endphp

<x-themes.shared.layout :page="$page">
  <div class="shell pt-24 pb-16 md:pt-28">
    <div class="max-w-3xl mx-auto">

      <div class="mb-8">
        <nav aria-label="Breadcrumb" class="mb-2">
          <ol class="flex items-center gap-2 text-xs text-[var(--ink-3)]">
            <li><a href="{{ route('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Home</a></li>
            <li aria-hidden="true">/</li>
            <li class="text-[var(--ink)] font-semibold" aria-current="page">Track Order</li>
          </ol>
        </nav>

        <h1 class="t-display-2 text-[var(--ink)]">Track your order &amp; waybill</h1>
        <p class="t-body text-xs text-[var(--ink-2)] mt-1">
          Look up courier dispatch progress with your order reference and the email or phone you ordered with.
        </p>
      </div>

      {{-- The lookup form. Visible labels on both boxes: a reference is a long
           string that is read out character by character on some devices, and a
           placeholder is not announced at all.

           Skipped entirely when the buyer arrived on their own signed link: the
           link is the authorisation, so asking them to prove it again would be
           asking for the same thing twice. --}}
      @if (! ($viaLink ?? false))
      <div class="card p-6 bg-[var(--surface)] border border-[var(--rule)] mb-8">
        <form method="GET" action="{{ route('storefront.track-order', ['slug' => $page->slug]) }}"
              class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
          <div class="sm:col-span-5">
            <label for="reference" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
              Order Reference Code
            </label>
            <input type="text"
                   id="reference"
                   name="reference"
                   value="{{ $reference }}"
                   required
                   class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] font-mono uppercase">
          </div>

          <div class="sm:col-span-4">
            <label for="contact" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
              Email or WhatsApp Phone
            </label>
            <input type="text"
                   id="contact"
                   name="contact"
                   value="{{ $contact }}"
                   required
                   class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] font-mono">
          </div>

          <div class="sm:col-span-3">
            <button type="submit"
                    class="w-full h-11 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center hover:opacity-90 transition-opacity">
              <span>Check Status</span>
            </button>
          </div>
        </form>
      </div>
      @endif

      @if ($searched && $order)
        <div class="card p-6 sm:p-8 bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] space-y-6">

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--rule)]">
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ $order->status->label() }}</span>
                <span class="text-[var(--rule)]" aria-hidden="true">·</span>
                <span class="font-mono text-xs font-bold text-[var(--ink)]">{{ $order->reference }}</span>
              </div>
              <h2 class="text-base font-bold text-[var(--ink)] mt-1">
                {{ $order->status->label() }} with {{ $page->name }}
              </h2>
            </div>

            <div class="text-right">
              <span class="t-meta block">Order Total</span>
              <span class="t-price-sm text-[var(--ink)]">{{ Money::format($order->total_kobo) }}</span>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] text-xs">
            <div>
              <span class="text-[var(--ink-3)] block">Placed</span>
              <span class="font-mono font-bold text-[var(--ink)]">{{ $order->created_at?->format('j M Y, g:ia') }}</span>
            </div>
            <div>
              <span class="text-[var(--ink-3)] block">Status</span>
              <span class="font-medium text-[var(--ink)]">{{ $order->status->label() }}</span>
            </div>
            <div>
              <span class="text-[var(--ink-3)] block">Source</span>
              <span class="font-medium text-[var(--ink)]">{{ $order->source->label() }}</span>
            </div>
          </div>

          <div>
            <h3 class="text-xs font-bold text-[var(--ink)] mb-4">Items</h3>
            <ul class="divide-y divide-[var(--rule)] border-y border-[var(--rule)]">
              @foreach ($order->items as $item)
                <li class="flex items-center justify-between gap-4 py-2.5 text-xs">
                  <span class="font-semibold text-[var(--ink)]">{{ $item->quantity }} x {{ $item->name }}</span>
                  <span class="font-bold text-[var(--ink)] tabular-nums">{{ Money::format($item->line_kobo) }}</span>
                </li>
              @endforeach
            </ul>
          </div>

          {{-- The history is written by the seller's own status changes, so it is
               the only tracking timeline that cannot claim a courier scan the
               shop never received. --}}
          @if ($order->history->isNotEmpty())
            <div>
              <h3 class="text-xs font-bold text-[var(--ink)] mb-4">Tracking History</h3>

              <ol class="relative border-l border-[var(--rule)] ml-3 space-y-6 text-xs">
                @foreach ($order->history->sortBy('created_at') as $entry)
                  <li class="pl-6 relative">
                    <span class="absolute -left-1.5 top-1 w-3 h-3 rounded-full {{ $loop->last ? 'bg-amber-500 animate-pulse' : 'bg-[var(--brand)]' }} ring-4 ring-[var(--card)]" aria-hidden="true"></span>
                    <div class="flex items-baseline justify-between gap-3">
                      <h4 class="font-bold text-[var(--ink)]">{{ $entry->to_status->label() }}</h4>
                      <time class="t-meta text-[11px]">{{ $entry->created_at?->format('j M, g:ia') }}</time>
                    </div>
                    @if ($entry->note)
                      <p class="text-[var(--ink-2)] mt-0.5">{{ $entry->note }}</p>
                    @endif
                  </li>
                @endforeach
              </ol>
            </div>
          @endif

          @if ($escrow)
            <div class="p-4 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] text-xs text-[var(--brand-text)]">
              <p class="font-bold">
                {{ Money::format($escrow->amount_kobo) }} is being held safely.
              </p>
              <p class="mt-1 text-[11px] text-[var(--ink-2)]">
                {{ config('app.name') }} is holding this until you are happy with the order.
              </p>
              <a href="{{ route('storefront.escrow', ['slug' => $page->slug, 'token' => $escrow->releaseToken()]) }}"
                 class="mt-3 inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold hover:opacity-90 transition-opacity">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                <span>{{ $escrow->isHeld() ? 'Manage Escrow Approval & Release →' : 'View this payment' }}</span>
              </a>
            </div>
          @endif

          <div class="pt-6 border-t border-[var(--rule)] flex flex-col sm:flex-row items-center justify-between gap-4">
            @if ($wa = filled($page->whatsapp_number) ? WhatsApp::link($page, 'Hello '.$page->name.', I have a question about order '.$order->reference.'.') : null)
              <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                 class="text-xs font-semibold text-[#25D366] hover:underline flex items-center gap-1.5">
                <span>Contact Dispatch on WhatsApp</span>
              </a>
            @else
              <span></span>
            @endif

            <p class="t-meta">Need a change? Message {{ $page->name }} quoting {{ $order->reference }}.</p>
          </div>
        </div>
      @elseif ($searched)
        <div class="p-5 rounded-[var(--radius)] bg-amber-500/10 border border-amber-500/30 text-xs">
          <p class="text-sm font-bold text-amber-900">We could not find that order.</p>
          <p class="mt-1 text-amber-800">
            Check the reference and the email or phone you ordered with. Both have to match the order.
          </p>
        </div>
      @endif
    </div>
  </div>
</x-themes.shared.layout>