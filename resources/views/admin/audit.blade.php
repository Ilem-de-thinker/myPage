<x-admin.layout title="Audit">
  <h1 class="font-display text-2xl font-extrabold tracking-tight">Audit</h1>
  <p class="mt-1 text-sm text-ink-2">
    Every admin decision, written before it took effect. These rows are immutable.
  </p>

  <div class="mt-6 overflow-hidden rounded-2xl border border-rule bg-card">
    <table class="w-full text-left text-xs">
      <thead class="border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3">
        <tr>
          <th class="px-4 py-3 font-semibold">When</th>
          <th class="px-4 py-3 font-semibold">Admin</th>
          <th class="px-4 py-3 font-semibold">Action</th>
          <th class="px-4 py-3 font-semibold">Subject</th>
          <th class="px-4 py-3 font-semibold">Note</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-rule">
        @forelse ($entries as $entry)
          <tr>
            <td class="whitespace-nowrap px-4 py-3 text-ink-3">{{ $entry->created_at?->format('j M H:i') }}</td>
            <td class="px-4 py-3 font-semibold text-ink">{{ $entry->admin?->name ?? 'system' }}</td>
            <td class="px-4 py-3">
              <span class="rounded-md bg-tint px-1.5 py-0.5 font-mono text-[10px]">{{ $entry->action }}</span>
            </td>
            <td class="px-4 py-3 text-ink-3">{{ $entry->subjectLabel() }}</td>
            <td class="px-4 py-3 text-ink-2">{{ $entry->note ?: '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-12 text-center text-ink-3">
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
