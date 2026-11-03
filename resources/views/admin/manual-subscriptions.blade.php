<x-admin.layout title="Manual transfers">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Manual transfers</h1>
  <p class="mt-1 text-sm text-ink-2">
    Sellers who said they paid by bank transfer. Nobody has seen the money yet —
    approving makes the plan live immediately.
  </p>

  @if (session('status'))
    <div class="mt-4 rounded-xl border border-ok-edge bg-ok-wash px-4 py-3 text-sm text-ok-text">
      {{ session('status') }}
    </div>
  @endif

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[56rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Seller</th>
          <th class="px-4 py-3 font-semibold">Plan</th>
          <th class="px-4 py-3 font-semibold">Claimed reference</th>
          <th class="px-4 py-3 font-semibold">Requested</th>
          <th class="px-4 py-3 font-semibold">Decision</th>
        </tr>
      </thead>
      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($subscriptions as $subscription)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Seller</span>
              <p class="font-bold text-ink">{{ $subscription->seller?->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $subscription->seller?->email }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Plan</span>
              <p class="font-semibold text-ink">{{ $subscription->plan?->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ \App\Shared\Support\Money::format($subscription->price_kobo) }} / {{ $subscription->plan?->interval }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Claimed reference</span>
              <p class="font-mono text-ink">{{ $subscription->gateway_reference }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Requested</span>
              <p class="text-ink-2">{{ $subscription->updated_at?->diffForHumans() }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Decision</span>
              <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.manual-subscriptions.approve', $subscription) }}">
                  @csrf
                  <button type="submit" class="rounded-lg bg-ink px-3 py-2 text-xs font-semibold text-paper hover:opacity-90">Approve</button>
                </form>
                <form method="POST" action="{{ route('admin.manual-subscriptions.decline', $subscription) }}">
                  @csrf
                  <button type="submit" class="rounded-lg border border-rule px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface">Decline</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-10 text-center text-sm text-ink-3">
              Nothing waiting. Manual transfers appear here the moment a seller claims one.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $subscriptions->links() }}</div>
</x-admin.layout>
