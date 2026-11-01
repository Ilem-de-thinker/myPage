<x-admin.layout title="Store reports">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Store reports</h1>
      <p class="mt-1 text-sm text-ink-3">Moderation input from buyers. This queue does not adjudicate or move money.</p>
    </div>
    <div class="flex gap-2 text-xs font-semibold">
      @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $value => $label)
        <a href="{{ route('admin.reports', ['status' => $value]) }}" class="rounded-lg px-3 py-2 {{ $activeStatus === $value ? 'bg-ink text-paper' : 'border border-rule bg-card text-ink-2' }}">{{ $label }}{{ $value !== 'all' ? ' '.($counts[$value] ?? 0) : '' }}</a>
      @endforeach
    </div>
  </div>

  <form method="GET" class="mt-5 flex flex-wrap items-end gap-3">
    <input type="hidden" name="status" value="{{ $activeStatus }}">
    <label class="text-xs font-semibold text-ink-2">Category
      <select name="category" class="mt-1 rounded-lg border border-rule bg-card px-3 py-2 text-xs text-ink">
        <option value="">All categories</option>
        @foreach (['fraud', 'not_delivered', 'not_as_described', 'misleading_listing', 'payment_problem', 'other'] as $category)
          <option value="{{ $category }}" @selected($activeCategory === $category)>{{ str_replace('_', ' ', $category) }}</option>
        @endforeach
      </select>
    </label>
    <button class="rounded-lg border border-rule bg-card px-4 py-2 text-xs font-semibold text-ink-2">Filter</button>
  </form>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[70rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Store</th>
          <th class="px-4 py-3 font-semibold">Category</th>
          <th class="px-4 py-3 font-semibold">Reporter</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold">Received</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($reports as $report)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Store</span>
              <p class="font-bold text-ink">{{ $report->storePage->name }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Category</span>
              <p class="font-semibold uppercase tracking-wide text-ink-2">{{ str_replace('_', ' ', $report->category) }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Reporter</span>
              <p class="text-ink-2">{{ $report->reporter_name ?: 'Anonymous' }}</p>
              @if ($report->reporter_contact)
                <p class="mt-0.5 text-ink-3">{{ $report->reporter_contact }}</p>
              @endif
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Status</span>
              <span class="rounded-full bg-tint px-3 py-1 text-xs font-semibold text-ink-2">{{ $report->status->label() }}</span>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Received</span>
              <p class="text-ink-2">{{ $report->created_at?->format('j M Y, g:ia') }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Actions</span>
              <details class="group">
                <summary class="cursor-pointer list-none text-xs font-semibold text-brand-text hover:underline">Review report</summary>
                <div class="mt-3 min-w-72 space-y-3">
                  <p class="whitespace-pre-line text-sm leading-6 text-ink-2">{{ $report->body }}</p>
                  <form method="POST" action="{{ route('admin.reports.update', $report) }}" class="space-y-2 border-t border-rule pt-3">
                    @csrf
                    <div>
                      <label for="status-{{ $report->id }}" class="block text-xs font-semibold text-ink-2">Decision</label>
                      <select id="status-{{ $report->id }}" name="status" class="mt-1 w-full rounded-lg border border-rule bg-paper px-3 py-2 text-xs text-ink">
                        @foreach (['open' => 'Open', 'resolved' => 'Resolve', 'dismissed' => 'Dismiss'] as $value => $label)
                          <option value="{{ $value }}" @selected($report->status->value === $value)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label for="note-{{ $report->id }}" class="block text-xs font-semibold text-ink-2">Internal note</label>
                      <input id="note-{{ $report->id }}" name="admin_note" value="{{ $report->admin_note }}" maxlength="2000" class="mt-1 w-full rounded-lg border border-rule bg-paper px-3 py-2 text-xs text-ink">
                    </div>
                    <button class="w-full rounded-lg bg-ink px-4 py-2 text-xs font-bold text-paper">Save</button>
                  </form>
                </div>
              </details>
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-dashed border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="6" class="block px-0 py-8 text-center text-sm text-ink-3 md:table-cell md:px-4 md:py-10">No reports in this view.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-6">{{ $reports->links() }}</div>
</x-admin.layout>
