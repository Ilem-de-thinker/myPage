<x-admin.layout title="Audit">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Audit</h1>
  <p class="mt-1 text-sm text-ink-2">
    Every admin decision, written before it took effect. These rows are immutable.
  </p>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">When</th>
          <th class="px-4 py-3 font-semibold">Admin</th>
          <th class="px-4 py-3 font-semibold">Action</th>
          <th class="px-4 py-3 font-semibold">Subject</th>
          <th class="px-4 py-3 font-semibold">Note</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($entries as $entry)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 text-ink-3 md:table-cell md:whitespace-nowrap md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">When</span>
              {{ $entry->created_at?->format('j M H:i') }}
            </td>
            <td class="block px-0 py-2 font-semibold text-ink md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Admin</span>
              {{ $entry->admin?->name ?? 'system' }}
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Action</span>
              <span class="rounded-md bg-tint px-1.5 py-0.5 font-mono text-[10px]">{{ $entry->action }}</span>
            </td>
            <td class="block px-0 py-2 text-ink-3 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Subject</span>
              {{ $entry->subjectLabel() }}
            </td>
            <td class="block px-0 py-2 text-ink-2 md:table-cell md:px-4 md:py-3">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Note</span>
              {{ $entry->note ?: '—' }}
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="5" class="block px-0 py-8 text-center text-ink-3 md:table-cell md:px-4 md:py-12">
              Nothing has been decided yet.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if ($entries->hasPages())
    <div class="mt-6">{{ $entries->links() }}</div>
  @endif
</x-admin.layout>
