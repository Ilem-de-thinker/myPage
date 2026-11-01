<x-admin.layout title="Badge review">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Verified badge</h1>
  <p class="mt-1 text-sm text-ink-2">Sellers who have proved they are a real business.</p>

  <div class="mt-5 flex gap-2 text-xs">
    @foreach (\App\Domains\Admin\Models\BadgeStatus::cases() as $status)
      <a href="{{ ($activeStatus ?? 'pending') === $status->value ? route('admin.badges') : route('admin.badges', ['status' => $status->value]) }}"
         @class([
             'rounded-lg px-3 py-1.5 font-semibold',
             'bg-ink text-paper' => ($activeStatus ?? 'pending') === $status->value,
             'border border-rule text-ink-2 hover:bg-surface' => ($activeStatus ?? 'pending') !== $status->value,
         ])>
        {{ $status->label() }}
        @if ($count = $counts[$status->value] ?? 0)
          <span class="ml-1 opacity-70">{{ $count }}</span>
        @endif
      </a>
    @endforeach
  </div>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[82rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Business</th>
          <th class="px-4 py-3 font-semibold">Seller</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold">Submitted</th>
          <th class="px-4 py-3 font-semibold">KYC details</th>
          <th class="px-4 py-3 font-semibold">Decision</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($applications as $application)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Business</span>
              <p class="font-bold text-ink">{{ $application->business_name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $application->storePage?->name }} · /{{ $application->storePage?->slug }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Seller</span>
              <p class="font-semibold text-ink">{{ $application->seller?->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $application->seller?->email }}</p>
              @if ($application->seller?->phone)
                <p class="mt-0.5 text-ink-3">{{ $application->seller->phone }}</p>
              @endif
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Status</span>
              <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $application->status->badgeClasses() }}">
                {{ $application->status->label() }}
              </span>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Submitted</span>
              <p class="text-ink-2">{{ $application->submitted_at?->format('j M Y') ?? '—' }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">KYC details</span>
              <details class="group">
                <summary class="cursor-pointer list-none text-xs font-semibold text-brand-text hover:underline">View KYC details</summary>
                <dl class="mt-2 space-y-1.5">
                  <div><dt class="text-ink-3">Registered legal name</dt><dd class="font-semibold text-ink">{{ $application->registered_legal_name ?: 'Not supplied' }}</dd></div>
                  <div><dt class="text-ink-3">CAC/RC or ID number</dt><dd class="font-mono font-semibold text-ink">{{ $application->registration_number ?: 'Not supplied' }}</dd></div>
                  <div><dt class="text-ink-3">Document</dt><dd class="font-semibold text-ink">{{ strtoupper($application->document_type) }}</dd></div>
                  <div><dt class="text-ink-3">Document expiry</dt><dd class="font-semibold text-ink">{{ $application->document_expires_at?->format('j M Y') ?: 'Not applicable / not supplied' }}</dd></div>
                  <div><dt class="text-ink-3">Retention until</dt><dd class="font-semibold text-ink">{{ $application->retention_until?->format('j M Y') ?: '—' }}</dd></div>
                </dl>
                @if ($application->note)
                  <p class="mt-2 rounded-lg bg-surface p-2 text-ink-2">{{ $application->note }}</p>
                @endif
                @if ($application->hasDocument())
                  <a href="{{ route('admin.badges.document', $application) }}" target="_blank" rel="noopener"
                     class="mt-2 inline-block text-xs font-semibold text-brand-text hover:underline">
                    View uploaded KYC document securely
                  </a>
                @else
                  <p class="mt-2 text-xs font-semibold text-danger-text">The retained document is no longer available.</p>
                @endif
              </details>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Decision</span>
              @if ($application->isPending())
                <details class="group">
                  <summary class="cursor-pointer list-none text-xs font-semibold text-brand-text hover:underline">Review application</summary>
                  <div class="mt-3 min-w-64 space-y-3">
                    <form method="POST" action="{{ route('admin.badges.decide', $application) }}" class="space-y-2">
                      @csrf
                      <input type="hidden" name="decision" value="approve">
                      <label class="block">
                        <span class="text-xs font-semibold text-ink">Note (optional)</span>
                        <input type="text" name="note" class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm">
                      </label>
                      <button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700">
                        Approve and badge the page
                      </button>
                    </form>

                    <form method="POST" action="{{ route('admin.badges.decide', $application) }}" class="space-y-2">
                      @csrf
                      <input type="hidden" name="decision" value="reject">
                      <label class="block">
                        <span class="text-xs font-semibold text-ink">Why are you rejecting?</span>
                        <textarea name="rejection_reason" rows="2" required
                                  class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm"
                                  placeholder="The CAC certificate is for a different business name"></textarea>
                        <span class="mt-1 block text-[11px] text-ink-3">The applicant sees this, so it has to be usable.</span>
                      </label>
                      <button class="w-full rounded-xl border border-danger-edge px-4 py-2.5 text-xs font-semibold text-danger-text hover:bg-danger-wash">
                        Reject
                      </button>
                    </form>
                  </div>
                </details>
              @else
                <div class="space-y-1">
                  @if ($application->rejection_reason)
                    <p class="text-ink-2">Rejected: {{ $application->rejection_reason }}</p>
                  @endif
                  <p class="text-ink-3">
                    Decided {{ $application->reviewed_at?->diffForHumans() }}
                    @if ($application->reviewed_by_seller_id)
                      by admin #{{ $application->reviewed_by_seller_id }}
                    @endif
                  </p>
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="6" class="block px-0 py-8 text-center md:table-cell md:px-4 md:py-16">
              <p class="text-sm text-ink-3">Nothing waiting.</p>
              <p class="mt-1 text-xs text-ink-3">Badge applications appear here as sellers submit them.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($applications->hasPages())
    <div class="mt-6">{{ $applications->links() }}</div>
  @endif
</x-admin.layout>
