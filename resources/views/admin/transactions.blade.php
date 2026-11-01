<x-admin.layout title="Transactions">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Transactions</h1>
      <p class="mt-1 text-sm text-ink-2">
        Every payment on the platform. The seller sees their own; this is where you answer
        "the money never arrived".
      </p>
    </div>

    <div class="text-right">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Taken</p>
      <p class="mt-0.5 text-lg font-extrabold tabular-naira">
        {{ \App\Shared\Support\Money::format($totals['paid_kobo']) }}
      </p>
    </div>
  </div>

  <div class="mt-5 flex flex-wrap items-center gap-2 text-xs">
    @foreach (\App\Domains\Payments\Enums\TransactionStatus::cases() as $status)
      <a href="{{ $activeStatus === $status->value ? route('admin.transactions') : route('admin.transactions', ['status' => $status->value]) }}"
         @class([
             'rounded-lg px-3 py-1.5 font-semibold',
             'bg-ink text-paper' => $activeStatus === $status->value,
             'border border-rule text-ink-2 hover:bg-surface' => $activeStatus !== $status->value,
         ])>
        {{ ucfirst($status->value) }}
        @if ($count = $counts[$status->value] ?? 0)
          <span class="ml-1 opacity-70">{{ $count }}</span>
        @endif
      </a>
    @endforeach

    <form method="GET" action="{{ route('admin.transactions') }}" class="ml-auto flex gap-2">
      @if ($activeStatus)
        <input type="hidden" name="status" value="{{ $activeStatus }}">
      @endif
      <input type="search" name="q" value="{{ $search }}" placeholder="Reference"
             class="rounded-lg border border-rule bg-card px-3 py-1.5 text-xs focus:border-brand focus:outline-none">
      <select name="gateway" class="rounded-lg border border-rule bg-card px-2 py-1.5 text-xs">
        <option value="">Any gateway</option>
        @foreach (['paystack', 'flutterwave', 'manual'] as $name)
          <option value="{{ $name }}" @selected($activeGateway === $name)>{{ ucfirst($name) }}</option>
        @endforeach
      </select>
      <button class="rounded-lg bg-ink px-3 py-1.5 font-semibold text-paper">Filter</button>
    </form>
  </div>

  <div class="mt-5 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[64rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Reference</th>
          <th class="px-4 py-3 font-semibold">Seller</th>
          <th class="px-4 py-3 font-semibold">For</th>
          <th class="px-4 py-3 text-right font-semibold">Amount</th>
          <th class="px-4 py-3 font-semibold">Gateway</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold">When</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($transactions as $transaction)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Reference</span>
              <p class="font-mono font-semibold text-ink">{{ $transaction->reference }}</p>
              @if ($transaction->gateway_payload['id'] ?? null)
                <p class="mt-0.5 font-mono text-[10px] text-ink-3">{{ $transaction->gateway_payload['id'] }}</p>
              @endif
            </td>
            <td class="block px-0 py-2 text-ink-2 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Seller</span>
              {{ $transaction->seller?->name ?? '—' }}
            </td>
            <td class="block px-0 py-2 text-ink-2 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">For</span>
              {{ class_basename((string) $transaction->payable_type) }} #{{ $transaction->payable_id }}
            </td>
            <td class="block px-0 py-2 font-bold tabular-naira text-ink md:table-cell md:px-4 md:py-3 md:text-right">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Amount</span>
              {{ \App\Shared\Support\Money::format($transaction->amount_kobo) }}
            </td>
            <td class="block px-0 py-2 text-ink-3 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Gateway</span>
              {{ ucfirst((string) $transaction->gateway) }}
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Status</span>
              <span @class([
                  'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                  'bg-ok-wash text-ok-text' => $transaction->status === \App\Domains\Payments\Enums\TransactionStatus::Paid,
                  'bg-warn-wash text-warn-text' => $transaction->status === \App\Domains\Payments\Enums\TransactionStatus::Pending,
                  'bg-danger-wash text-danger-text' => $transaction->status === \App\Domains\Payments\Enums\TransactionStatus::Failed,
                  'bg-tint text-ink-2' => $transaction->status === \App\Domains\Payments\Enums\TransactionStatus::Refunded,
              ])>
                {{ $transaction->status->value }}
              </span>
            </td>
            <td class="block px-0 py-2 text-ink-3 md:table-cell md:whitespace-nowrap md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">When</span>
              {{ $transaction->created_at->format('j M H:i') }}
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="7" class="block px-0 py-8 text-center text-ink-3 md:table-cell md:px-4 md:py-12">No transactions match those filters.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($transactions->hasPages())
    <div class="mt-6">{{ $transactions->links() }}</div>
  @endif
</x-admin.layout>
