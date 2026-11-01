<x-admin.layout title="Team">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Team</h1>
  <p class="mt-1 max-w-2xl text-sm text-ink-2">
    Staff admins run stores, badges, transactions, reports and support. Core admins
    also control settings, plans, feature flags, gateway connections, grace
    overrides and this team page.
  </p>

  <div class="mt-5 rounded-xl border border-rule bg-surface px-4 py-3 text-xs text-ink-2">
    The platform always keeps at least one active core admin. Demoting the last one is refused,
    including when that is you.
  </div>

  <form method="GET" action="{{ route('admin.team') }}" class="mt-5 max-w-sm">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or email"
           class="w-full rounded-xl border border-rule bg-card px-4 py-2.5 text-sm focus:border-brand focus:outline-none">
  </form>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[48rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Person</th>
          <th class="px-4 py-3 font-semibold">Access</th>
          <th class="px-4 py-3 font-semibold">Change role</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($admins as $admin)
          @php($current = $admin->adminRole())
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Person</span>
              <p class="font-bold text-ink">
                {{ $admin->name }}
                @if (auth()->id() === $admin->id)
                  <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-ink-3">you</span>
                @endif
              </p>
              <p class="mt-0.5 text-ink-3">{{ $admin->email }}</p>
            </td>

            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Access</span>
              <span @class([
                  'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                  'bg-brand-wash text-brand-text' => $current?->isCore(),
                  'bg-tint text-ink-2' => ! $current?->isCore(),
              ])>
                {{ $current?->label() ?? 'Not an admin' }}
              </span>
              <span class="ml-1 text-ink-3">{{ $admin->status }}</span>
            </td>

            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Change role</span>
              <form method="POST" action="{{ route('admin.team.update', $admin) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <select name="role" class="rounded-xl border border-rule bg-card px-3 py-2 text-xs focus:border-brand focus:outline-none">
                  <option value="core_admin" @selected($current?->value === 'core_admin')>Core admin</option>
                  <option value="staff_admin" @selected($current?->value === 'staff_admin')>Staff admin</option>
                  <option value="" @selected($current === null)>No admin access</option>
                </select>
                <button type="submit" class="inline-flex min-h-9 items-center rounded-xl bg-ink px-3 py-2 text-xs font-semibold text-paper hover:bg-ink-2">
                  Save
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="3" class="block px-0 py-8 text-center text-ink-3 md:table-cell md:px-4 md:py-16">
              No admins match that search.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($admins->hasPages())
    <div class="mt-6">{{ $admins->links() }}</div>
  @endif
</x-admin.layout>
