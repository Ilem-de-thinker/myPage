@php
    use App\Domains\Billing\Models\EscrowStatus;
    use App\Shared\Support\Money;

    $isHeld = $escrow->isHeld();
@endphp

<x-themes.shared.layout :page="$page">
  <div x-data="{ confirmReleaseModal: false }">
    <div class="shell pt-24 pb-16 md:pt-28">
      <div class="max-w-3xl mx-auto">

        <div class="mb-8">
          <nav aria-label="Breadcrumb" class="mb-2">
            <ol class="flex items-center gap-2 text-xs text-[var(--ink-3)]">
              <li><a href="{{ route('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Home</a></li>
              <li aria-hidden="true">/</li>
              <li><a href="{{ route('storefront.track-order', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)]">Track Order</a></li>
              <li aria-hidden="true">/</li>
              <li class="text-[var(--ink)] font-semibold" aria-current="page">Escrow Protocol</li>
            </ol>
          </nav>

          <h1 class="t-display-2 text-[var(--ink)]">Escrow Vault Token Management</h1>
          <p class="t-body text-sm font-semibold text-[var(--ink)] mt-1">Your payment is protected</p>
          <p class="t-body text-xs text-[var(--ink-2)] mt-1">
            {{ config('app.name') }} is holding {{ Money::format($escrow->amount_kobo) }} for
            {{ $page->name }} until you say you got what you paid for. Payouts are released only after confirmed inspection.
          </p>
        </div>

        {{-- The one page a buyer is given a token for, so the state of the money
             is the first thing on it: what it is, who is holding it and what has
             happened to it so far. --}}
        <div class="card p-6 sm:p-8 bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] space-y-6">

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--rule)]">
            <div>
              <span class="text-xs font-mono font-bold text-[var(--ink-3)]">TOKEN: #{{ Str::upper(Str::substr($escrow->releaseToken(), 0, 12)) }}</span>
              @if ($order)
                <h2 class="text-base font-bold text-[var(--ink)] mt-0.5">Order Ref: {{ $order->reference }}</h2>
              @endif
            </div>
            <div class="flex items-center gap-2">
              <span class="t-meta">Vault Balance:</span>
              <span class="t-price-sm text-[var(--ink)]">{{ Money::format($escrow->amount_kobo) }}</span>
            </div>
          </div>

          @if (session('status'))
            <div role="status" class="p-4 rounded-[var(--radius)] bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-900">
              {{ session('status') }}
            </div>
          @endif

          @if ($errors->any())
            <div role="alert" class="p-4 rounded-[var(--radius)] bg-red-500/10 border border-red-500/30 text-xs text-red-800">
              {{ $errors->first() }}
            </div>
          @endif

          {{-- State 1: held. The buyer still holds the money. --}}
          @if ($isHeld)
            <div class="p-5 rounded-[var(--radius)] bg-amber-500/10 border border-amber-500/30 text-xs">
              <div class="flex items-center gap-2 mb-2">
                <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse" aria-hidden="true"></span>
                <h3 class="font-bold text-[var(--ink)]">Payment Secured in Escrow Vault</h3>
                <span class="ml-auto font-mono text-[11px] font-bold text-amber-700 dark:text-amber-300">{{ $escrow->status->label() }}</span>
              </div>
              <p class="text-[var(--ink-2)] leading-relaxed">
                Your payment of {{ Money::format($escrow->amount_kobo) }} is locked in institutional escrow.
                {{ $page->name }} receives it only after you inspect the order and authorise the release below.
              </p>
            </div>
          @elseif ($escrow->status === EscrowStatus::Frozen)
            <div class="p-5 rounded-[var(--radius)] bg-red-500/10 border border-red-500/30 text-xs">
              <div class="flex items-center gap-2 mb-2">
                <span class="w-3 h-3 rounded-full bg-red-500" aria-hidden="true"></span>
                <h3 class="font-bold text-[var(--ink)]">Dispute Chamber Active</h3>
                <span class="ml-auto font-mono text-[11px] font-bold text-red-600">Locked</span>
              </div>
              <p class="text-[var(--ink-2)] leading-relaxed">
                {{ $escrow->dispute_reason ?: 'A dispute is open on this payment.' }}
                All escrow funds are frozen until the dispute is resolved. We will write to
                {{ $order?->customer_email ?: 'you' }} once an admin decides.
              </p>
            </div>
          @else
            <div class="p-5 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] text-xs">
              <div class="flex items-center gap-2 mb-2">
                <svg class="w-4 h-4 text-[var(--brand-text)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <h3 class="font-bold text-[var(--ink)]">
                  {{ $escrow->status === EscrowStatus::Released ? 'Escrow Payout Settled to Merchant' : 'Escrow Returned to You' }}
                </h3>
                <span class="ml-auto font-mono text-[11px] font-bold text-[var(--brand-text)]">{{ $escrow->status->label() }}</span>
              </div>
              <p class="text-[var(--ink-2)] leading-relaxed">
                @if ($escrow->status === EscrowStatus::Released)
                  Payout of {{ Money::format($escrow->amount_kobo) }} has been transferred to {{ $page->name }}
                  {{ $escrow->released_at?->diffForHumans() }}. Thank you for confirming.
                @else
                  This payment was returned to your card. It can take a few working days to show.
                @endif
              </p>
            </div>
          @endif

          @if ($order && $order->items->isNotEmpty())
            <ul class="divide-y divide-[var(--rule)] border-y border-[var(--rule)]">
              @foreach ($order->items as $item)
                <li class="flex items-center justify-between gap-4 py-2.5 text-xs">
                  <span class="font-semibold text-[var(--ink)]">{{ $item->quantity }} x {{ $item->name }}</span>
                  <span class="font-bold text-[var(--ink)] tabular-nums">{{ Money::format($item->line_kobo) }}</span>
                </li>
              @endforeach
            </ul>
          @endif

          @if ($isHeld && $escrow->auto_releases_at)
            <p class="p-3 rounded-[var(--radius)] bg-[var(--surface)] text-xs text-[var(--ink-2)]">
              If you do nothing, this releases itself to {{ $page->name }}
              {{ $escrow->auto_releases_at->diffForHumans() }}
              ({{ $escrow->auto_releases_at->format('j M Y') }}). Releasing early just means you got it sooner.
            </p>
          @endif

          @if ($isHeld)
            <div class="pt-4 border-t border-[var(--rule)] space-y-4">
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                <div>
                  <h3 class="text-sm font-bold text-[var(--ink)]">Have you received and accepted your order?</h3>
                  <p class="t-meta text-xs mt-0.5">I got it — authorise release of {{ Money::format($escrow->amount_kobo) }} to {{ $page->name }}.</p>
                </div>

                <button type="button"
                        @click="confirmReleaseModal = true"
                        class="h-12 px-6 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity whitespace-nowrap shadow-sm shrink-0">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                  <span>Release Escrow to Merchant</span>
                </button>
              </div>
            </div>

            {{--
                The dispute chamber. A dispute is a claim, not a refund request:
                the buyer still does not know the seller is at fault, and this does
                not refund anyone. It freezes the money so somebody has to look.
            --}}
            <details class="border border-[var(--rule)] rounded-[var(--radius)] p-4 bg-[var(--surface)] group">
              <summary class="flex items-center justify-between cursor-pointer list-none text-xs font-bold text-[var(--ink)]">
                <div class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                  <span>Need to open a dispute? (Wrong size, damaged goods, or non-delivery)</span>
                </div>
                <span class="text-xs text-[var(--ink-3)] group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
              </summary>

              <form method="POST" action="{{ route('storefront.escrow.dispute', ['slug' => $page->slug, 'token' => $escrow->releaseToken()]) }}"
                    class="mt-4 pt-4 border-t border-[var(--rule)] space-y-4 text-xs">
                @csrf

                <div>
                  <label for="dispute-reason" class="block font-bold text-[var(--ink)] mb-1">
                    What went wrong? <span class="text-red-500">*</span>
                  </label>
                  <textarea id="dispute-reason"
                            name="reason"
                            rows="3"
                            required
                            minlength="20"
                            maxlength="1000"
                            placeholder="Describe the discrepancy and the condition the parcel arrived in."
                            class="w-full p-3 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)]">{{ old('reason') }}</textarea>
                  <span class="t-meta mt-1 block">At least a sentence, please. A person reads this, not a robot.</span>
                </div>

                <div class="flex items-center justify-between pt-2 gap-4">
                  <span class="t-meta">Escrow payout freezes instantly upon submission.</span>
                  <button type="submit" class="px-5 py-2.5 rounded-[var(--radius)] bg-red-600 text-white font-bold text-xs hover:bg-red-700 transition-colors shrink-0">
                    Open a Dispute
                  </button>
                </div>
              </form>
            </details>
          @endif
        </div>

        <p class="mt-6 t-body text-xs text-[var(--ink-2)] text-center">
          Questions? Message {{ $page->name }} on WhatsApp{{ $order ? ' quoting '.$order->reference : '' }}.
        </p>
      </div>
    </div>

    {{-- Releasing money cannot be undone, so it is confirmed once, deliberately,
         on a dialog rather than a browser confirm() the seller cannot style or
         label. The form posts for real; the dialog only gates it. --}}
    <div x-show="confirmReleaseModal"
         x-transition.opacity.duration.200ms
         @keydown.escape.window="confirmReleaseModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="confirm-release-title">

      <div @click.away="confirmReleaseModal = false"
           class="w-full max-w-md bg-[var(--card)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-lift)] space-y-4">

        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-[var(--brand-wash)] text-[var(--brand-text)] flex items-center justify-center shrink-0 border border-[var(--brand-edge)]">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <div>
            <h3 id="confirm-release-title" class="text-base font-bold text-[var(--ink)]">Confirm Escrow Release</h3>
            @if ($order)
              <p class="t-meta text-xs">Order Ref: {{ $order->reference }}</p>
            @endif
          </div>
        </div>

        <p class="text-xs text-[var(--ink-2)] leading-relaxed">
          Are you sure you want to release <strong>{{ Money::format($escrow->amount_kobo) }}</strong> to {{ $page->name }}?
          This action confirms you have received the order and are fully satisfied with it. You cannot take it back.
        </p>

        <form method="POST" action="{{ route('storefront.escrow.release', ['slug' => $page->slug, 'token' => $escrow->releaseToken()]) }}"
              class="flex items-center justify-end gap-3 pt-3 border-t border-[var(--rule)]">
          @csrf
          <button type="button" @click="confirmReleaseModal = false" class="px-4 py-2 text-xs font-bold text-[var(--ink-2)] hover:text-[var(--ink)]">Cancel</button>
          <button type="submit" class="px-5 py-2 text-xs font-bold rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)]">
            Yes, Release Payout
          </button>
        </form>
      </div>
    </div>
  </div>
</x-themes.shared.layout>