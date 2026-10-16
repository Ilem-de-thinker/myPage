<x-admin.layout title="Payouts">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Payouts</h1>
      <p class="mt-1 text-sm text-ink-2">
        Only failures and in-flight transfers. Successful payouts are the seller's business,
        not yours.
      </p>
    </div>

    <div class="text-right">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Stranded in the payouts pool</p>
      <p @class([
          'mt-0.5 text-lg font-extrabold tabular-naira',
          'text-danger-text' => $stranded !== 0,
          'text-ink' => $stranded === 0,
      ])>
        {{ \App\Shared\Support\Money::format($stranded) }}
      </p>
      <p class="text-[11px] text-ink-3">{{ $failedCount }} failed</p>
    </div>
  </div>

  @if ($stranded !== 0)
    <div class="mt-5 rounded-xl border border-warn-edge bg-warn-wash px-4 py-3 text-xs text-warn-text">
      Money is sitting in the payouts account that has not reached a seller yet. A failed
      transfer returns to the seller automatically; this is what is genuinely in transit.
    </div>
  @endif

  <div class="mt-5 space-y-3">
    @forelse ($payouts as $payout)
      <div class="rounded-2xl border border-rule bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">
              {{ \App\Shared\Support\Money::format($payout->amount_kobo) }}
              <span class="ml-2 font-mono text-xs font-normal text-ink-3">{{ $payout->reference }}</span>
            </p>
            <p class="mt-0.5 text-xs text-ink-3">
              {{ $payout->seller?->name ?? '—' }} &rarr;
              <span class="font-semibold">{{ $payout->account_name }}</span>
              ({{ $payout->bank_code }} {{ $payout->account_number }})
            </p>
            <p class="mt-0.5 text-xs text-ink-3">
              Requested {{ $payout->created_at->diffForHumans() }} via {{ $payout->gateway }}
              @if ($payout->gateway_reference)
                &middot; gateway ref {{ $payout->gateway_reference }}
              @endif
            </p>

            @if ($payout->failure_reason)
              <p class="mt-2 rounded-lg bg-danger-wash px-3 py-2 text-xs text-danger-text">
                {{ $payout->failure_reason }}
              </p>
            @endif
          </div>

          <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ match ($payout->status) {
              \App\Domains\Payments\Enums\PayoutStatus::Paid => 'bg-ok-wash text-ok-text',
              \App\Domains\Payments\Enums\PayoutStatus::Pending => 'bg-warn-wash text-warn-text',
              default => 'bg-danger-wash text-danger-text',
          } }}">
            {{ $payout->status->label() }}
          </span>
        </div>

        @if ($payout->status === \App\Domains\Payments\Enums\PayoutStatus::Failed)
          <p class="mt-4 border-t border-rule pt-4 text-xs text-ink-3">
            A failed payout has already returned the money to the seller's balance, so nothing
            is stuck. They can request it again from their own dashboard — no admin action needed.
          </p>
        @endif
      </div>
    @empty
      <div class="rounded-2xl border border-rule bg-card px-6 py-16 text-center">
        <p class="text-sm text-ink-3">No payouts need attention.</p>
        <p class="mt-1 text-xs text-ink-3">Failed and in-flight transfers appear here.</p>
      </div>
    @endforelse
  </div>

  @if ($payouts->hasPages())
    <div class="mt-6">{{ $payouts->links() }}</div>
  @endif
</x-admin.layout>
