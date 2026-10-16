<x-admin.layout title="Escrow">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Escrow</h1>
      <p class="mt-1 text-sm text-ink-2">
        Buyer money we are holding. Frozen rows are disputes: only an admin can move them.
      </p>
    </div>

    <div class="flex gap-2 text-xs">
      @foreach (\App\Domains\Billing\Models\EscrowStatus::cases() as $status)
        <a href="{{ $activeStatus === $status->value ? route('admin.escrows') : route('admin.escrows', ['status' => $status->value]) }}"
           @class([
               'rounded-lg px-3 py-1.5 font-semibold',
               'bg-ink text-white' => $activeStatus === $status->value,
               'border border-rule text-ink-2 hover:bg-surface' => $activeStatus !== $status->value,
           ])>
          {{ $status->label() }}
          @if ($count = $counts[$status->value] ?? 0)
            <span class="ml-1 opacity-70">{{ $count }}</span>
          @endif
        </a>
      @endforeach
    </div>
  </div>

  <div class="mt-6 space-y-3">
    @forelse ($escrows as $escrow)
      <div class="rounded-2xl border border-rule bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">
              {{ \App\Shared\Support\Money::format($escrow->amount_kobo) }}
              <span class="ml-2 text-xs font-normal text-ink-3">seller {{ $escrow->seller?->name ?? '—' }}</span>
            </p>
            <p class="mt-0.5 text-xs text-ink-3">
              {{ class_basename($escrow->payable_type) }} #{{ $escrow->payable_id }} &middot;
              held {{ $escrow->held_at?->diffForHumans() ?? '—' }}
              @if ($escrow->auto_releases_at && $escrow->isHeld())
                &middot; auto-releases {{ $escrow->auto_releases_at->diffForHumans() }}
              @endif
            </p>

            @if ($escrow->dispute_reason)
              <p class="mt-2 rounded-lg bg-danger-wash p-3 text-xs text-danger-text">
                <span class="font-semibold">Disputed:</span> {{ $escrow->dispute_reason }}
              </p>
            @endif
          </div>

          <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $escrow->status->badgeClasses() }}">
            {{ $escrow->status->label() }}
          </span>
        </div>

        @if ($escrow->status === \App\Domains\Billing\Models\EscrowStatus::Frozen)
          <form method="POST" action="{{ route('admin.escrows.resolve', $escrow) }}"
                class="mt-4 grid gap-3 border-t border-rule pt-4 sm:grid-cols-[1fr_auto] sm:items-end">
            @csrf
            <label class="block">
              <span class="text-xs font-semibold text-ink">What did you decide, and why?</span>
              <textarea name="note" rows="2" required
                        class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm"
                        placeholder="Photo evidence shows the order arrived as described"></textarea>
            </label>

            <div class="flex gap-2">
              <button name="decision" value="release_to_seller"
                        class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700">
                Pay the seller
              </button>
              <button name="decision" value="refund_buyer"
                        class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink hover:bg-surface">
                Refund the buyer
              </button>
            </div>
          </form>
        @endif
      </div>
    @empty
      <div class="rounded-2xl border border-rule bg-card px-6 py-16 text-center">
        <p class="text-sm text-ink-3">No escrow with that status.</p>
      </div>
    @endforelse
  </div>

  @if ($escrows->hasPages())
    <div class="mt-6">{{ $escrows->links() }}</div>
  @endif
</x-admin.layout>