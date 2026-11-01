<x-admin.layout title="Gateway health">
  <div>
    <h1 class="font-display text-2xl font-extrabold tracking-tight">Seller gateway health</h1>
    <p class="mt-1 text-sm text-ink-3">Buyer payments go directly to these sellers. This screen only reports credential and webhook delivery health.</p>
  </div>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[48rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-5 py-3 font-semibold">Seller</th>
          <th class="px-5 py-3 font-semibold">Gateway</th>
          <th class="px-5 py-3 font-semibold">Credentials</th>
          <th class="px-5 py-3 font-semibold">Last webhook</th>
        </tr>
      </thead>
      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($gateways as $gateway)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-5 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Seller</span>
              <p class="font-semibold text-ink">{{ $gateway->seller->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $gateway->seller->email }}</p>
            </td>
            <td class="block px-0 py-2 font-semibold capitalize text-ink md:table-cell md:px-5 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Gateway</span>
              {{ $gateway->gateway }} · {{ $gateway->environment }}
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-5 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Credentials</span>
              @if ($gateway->last_failed_at && $gateway->verified_at && $gateway->last_failed_at->gt($gateway->verified_at))
                <span class="rounded-full bg-danger-wash px-2.5 py-1 font-semibold text-danger-text">Failed</span>
              @else
                <span class="rounded-full bg-ok-wash px-2.5 py-1 font-semibold text-ok-text">Verified</span>
              @endif
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-5 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Last webhook</span>
              @if ($gateway->last_webhook_at)
                <span class="font-semibold text-ok-text">Received {{ $gateway->last_webhook_at->format('j M Y, H:i') }}</span>
              @else
                <span class="font-semibold text-warn-text">No verified delivery yet</span>
              @endif
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="4" class="block px-0 py-8 text-center text-ink-3 md:table-cell md:px-5 md:py-10">No seller gateways have been connected.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-6">{{ $gateways->links() }}</div>
</x-admin.layout>
