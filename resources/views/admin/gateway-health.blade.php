<x-admin.layout title="Gateway health">
  <div>
    <h1 class="font-display text-2xl font-extrabold tracking-tight">Seller gateway health</h1>
    <p class="mt-1 text-sm text-ink-3">Buyer payments go directly to these sellers. This screen only reports credential and webhook delivery health.</p>
  </div>

  <div class="mt-6 overflow-hidden rounded-2xl border border-rule bg-card">
    <div class="overflow-x-auto">
      <table class="min-w-full text-left text-xs">
        <thead class="border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3">
          <tr>
            <th class="px-5 py-3 font-semibold">Seller</th>
            <th class="px-5 py-3 font-semibold">Gateway</th>
            <th class="px-5 py-3 font-semibold">Credentials</th>
            <th class="px-5 py-3 font-semibold">Last webhook</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-rule">
          @forelse ($gateways as $gateway)
            <tr>
              <td class="px-5 py-4">
                <p class="font-semibold text-ink">{{ $gateway->seller->name }}</p>
                <p class="mt-0.5 text-ink-3">{{ $gateway->seller->email }}</p>
              </td>
              <td class="px-5 py-4 font-semibold capitalize text-ink">{{ $gateway->gateway }} · {{ $gateway->environment }}</td>
              <td class="px-5 py-4">
                @if ($gateway->last_failed_at && $gateway->verified_at && $gateway->last_failed_at->gt($gateway->verified_at))
                  <span class="rounded-full bg-danger-wash px-2.5 py-1 font-semibold text-danger-text">Failed</span>
                @else
                  <span class="rounded-full bg-ok-wash px-2.5 py-1 font-semibold text-ok-text">Verified</span>
                @endif
              </td>
              <td class="px-5 py-4">
                @if ($gateway->last_webhook_at)
                  <span class="font-semibold text-ok-text">Received {{ $gateway->last_webhook_at->format('j M Y, H:i') }}</span>
                @else
                  <span class="font-semibold text-amber-700">No verified delivery yet</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-5 py-10 text-center text-ink-3">No seller gateways have been connected.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-6">{{ $gateways->links() }}</div>
</x-admin.layout>
