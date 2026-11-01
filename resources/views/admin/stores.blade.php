<x-admin.layout title="Stores">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Stores</h1>
  <p class="mt-1 text-sm text-ink-2">Every page on the platform.</p>

  <form method="GET" action="{{ route('admin.stores') }}" class="mt-5 max-w-sm">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or slug"
           class="w-full rounded-xl border border-rule bg-card px-4 py-2.5 text-sm focus:border-brand focus:outline-none">
  </form>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[72rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Store</th>
          <th class="px-4 py-3 font-semibold">Seller</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold">Directory</th>
          <th class="px-4 py-3 font-semibold">Joined</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($pages as $page)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Store</span>
              <p class="font-bold text-ink">{{ $page->name }}</p>
              <p class="mt-0.5 text-ink-3">/{{ $page->slug }} · {{ ucfirst($page->theme->value) }} theme</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Seller</span>
              <p class="font-semibold text-ink">{{ $page->seller?->name }}</p>
              <p class="mt-0.5 text-ink-3">{{ $page->seller?->email }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Status</span>
              <span @class([
                  'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                  'bg-ok-wash text-ok-text' => $page->isPublished(),
                  'bg-tint text-ink-2' => $page->status->value === 'draft',
                  'bg-danger-wash text-danger-text' => $page->status->value === 'suspended',
              ])>
                {{ $page->status->value }}
              </span>
              @php $subscription = $page->seller?->subscriptions->first(); @endphp
              @if ($subscription)
                @php $merchantStatus = $subscription->merchantStatus(); @endphp
                <p class="mt-2">
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $merchantStatus->badgeClasses() }}">{{ $merchantStatus->label() }}</span>
                </p>
                @if ($subscription->grace_override_until)
                  <p class="mt-1 text-[10px] text-ink-3">Override until {{ $subscription->grace_override_until->format('j M Y') }}</p>
                @endif
              @endif
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Directory</span>
              <p class="font-semibold text-ink">{{ $page->is_directory_opted_in ? ($page->is_featured ? 'Approved and featured' : 'Approved') : 'Not listed' }}</p>
              <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ([['approve', 'Approve'], ['suspend', 'Remove'], ['feature', 'Feature'], ['unfeature', 'Unfeature']] as [$action, $label])
                  <form method="POST" action="{{ route('admin.stores.directory', $page) }}">
                    @csrf
                    <input type="hidden" name="directory_action" value="{{ $action }}">
                    <button class="rounded-lg border border-rule bg-card px-2.5 py-1 text-[10px] font-semibold text-ink hover:border-brand">{{ $label }}</button>
                  </form>
                @endforeach
              </div>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Joined</span>
              <p class="text-ink-2">{{ $page->created_at->format('j M Y') }}</p>
              @if ($page->published_at)
                <p class="mt-0.5 text-ink-3">Live since {{ $page->published_at->format('j M Y') }}</p>
              @endif
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Actions</span>
              @if ($page->status->value === 'suspended')
                <form method="POST" action="{{ route('admin.stores.reinstate', $page) }}">
                  @csrf
                  <input type="hidden" name="note" value="Reinstated by admin">
                  <button class="rounded-xl border border-rule px-4 py-2 text-xs font-semibold text-ink hover:bg-surface">
                    Reinstate as draft
                  </button>
                </form>
              @else
                <details class="group">
                  <summary class="cursor-pointer list-none rounded-xl border border-danger-edge px-4 py-2 text-center text-xs font-semibold text-danger-text hover:bg-danger-wash">Suspend store</summary>
                  <form method="POST" action="{{ route('admin.stores.suspend', $page) }}" class="mt-2 min-w-64 space-y-2">
                    @csrf
                    <label class="block">
                      <span class="text-[11px] font-semibold text-ink-2">Reason (required, kept on the audit log)</span>
                      <input type="text" name="reason" required
                             class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm"
                             placeholder="Chargeback fraud">
                    </label>
                    <button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700">
                      Suspend
                    </button>
                  </form>
                </details>
              @endif

              @if ($subscription)
                <details class="mt-2">
                  <summary class="cursor-pointer list-none rounded-xl border border-rule px-4 py-2 text-center text-xs font-semibold text-ink hover:bg-surface">Grace override</summary>
                  <form method="POST" action="{{ route('admin.stores.grace', $page) }}" class="mt-2 min-w-64 space-y-2">
                    @csrf
                    <label class="block">
                      <span class="text-[11px] font-semibold text-ink-2">Access until (leave empty to clear)</span>
                      <input type="datetime-local" name="grace_until"
                             value="{{ $subscription->grace_override_until?->format('Y-m-d\TH:i') }}"
                             class="mt-1 w-full rounded-lg border border-rule px-3 py-2 text-sm">
                      @error('grace_until') <span class="mt-1 block text-[11px] text-danger-text">{{ $message }}</span> @enderror
                    </label>
                    <button class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-hover">
                      Save grace
                    </button>
                  </form>
                </details>
              @endif
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="6" class="block px-0 py-8 text-center text-ink-3 md:table-cell md:px-4 md:py-16">No stores match that search.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($pages->hasPages())
    <div class="mt-6">{{ $pages->links() }}</div>
  @endif
</x-admin.layout>
