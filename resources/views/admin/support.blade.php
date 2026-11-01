<x-admin.layout title="Support">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Support queue</h1>
      <p class="mt-1 text-sm text-ink-3">Buyer questions and seller requests sent to mypage.ng.</p>
    </div>
    <div class="flex flex-wrap gap-2 text-xs font-semibold">
      @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $value => $label)
        <a href="{{ route('admin.support', ['status' => $value, 'source' => $activeSource]) }}" class="rounded-lg px-3 py-2 {{ $activeStatus === $value ? 'bg-ink text-paper' : 'border border-rule bg-card text-ink-2' }}">{{ $label }}{{ $value !== 'all' ? ' '.($counts[$value] ?? 0) : '' }}</a>
      @endforeach
    </div>
  </div>

  <div class="mt-5 flex gap-2 text-xs font-semibold">
    @foreach (['' => 'All', 'buyer' => 'Platform buyers', 'store' => 'Store buyers', 'seller' => 'Sellers'] as $value => $label)
      <a href="{{ route('admin.support', ['status' => $activeStatus, 'source' => $value]) }}" class="rounded-lg border border-rule px-3 py-2 {{ $activeSource === $value || ($value === '' && ! $activeSource) ? 'bg-tint text-ink' : 'bg-card text-ink-2' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="mt-6 md:overflow-x-auto md:rounded-2xl md:border md:border-rule md:bg-card">
    <table class="block w-full text-left text-xs md:table md:min-w-[78rem]">
      <thead class="hidden border-b border-rule bg-surface text-[11px] uppercase tracking-wide text-ink-3 md:table-header-group">
        <tr>
          <th class="px-4 py-3 font-semibold">Ticket</th>
          <th class="px-4 py-3 font-semibold">Subject</th>
          <th class="px-4 py-3 font-semibold">Requester</th>
          <th class="px-4 py-3 font-semibold">Source</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold">Messages</th>
          <th class="px-4 py-3 font-semibold">Received</th>
          <th class="px-4 py-3 font-semibold">Actions</th>
        </tr>
      </thead>

      <tbody class="block space-y-3 md:table-row-group md:space-y-0 md:divide-y md:divide-rule">
        @forelse ($requests as $supportRequest)
          <tr class="block rounded-2xl border border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Ticket</span>
              <a href="{{ route('admin.support.show', $supportRequest) }}" class="font-mono font-semibold text-brand-text hover:underline">{{ $supportRequest->ticket_number }}</a>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Subject</span>
              <a href="{{ route('admin.support.show', $supportRequest) }}" class="font-bold text-ink hover:text-brand-text">{{ $supportRequest->subject }}</a>
              @if ($supportRequest->storePage)
                <p class="mt-0.5 text-ink-3">Store: {{ $supportRequest->storePage->name }}</p>
              @endif
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Requester</span>
              <p class="text-ink-2">{{ $supportRequest->requester_name ?: ($supportRequest->seller?->name ?? 'Anonymous') }}</p>
              <p class="mt-0.5 text-ink-3">{{ $supportRequest->requester_contact ?: 'No contact supplied' }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Source</span>
              <span class="rounded-full bg-tint px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-ink-2">{{ $supportRequest->source }}</span>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Status</span>
              <span class="rounded-full bg-tint px-3 py-1 text-xs font-semibold text-ink-2">{{ $supportRequest->status->label() }}</span>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Messages</span>
              <p class="text-ink-2">{{ $supportRequest->messages->count() }} {{ $supportRequest->messages->count() === 1 ? 'message' : 'messages' }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Received</span>
              <p class="text-ink-2">{{ $supportRequest->created_at?->format('j M Y, g:ia') }}</p>
            </td>
            <td class="block px-0 py-2 md:table-cell md:px-4 md:py-4">
              <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-ink-3 md:hidden">Actions</span>
              <a href="{{ route('admin.support.show', $supportRequest) }}" class="inline-flex rounded-xl border border-rule px-4 py-2 text-xs font-semibold text-ink-2 hover:bg-surface">Open ticket</a>
            </td>
          </tr>
        @empty
          <tr class="block rounded-2xl border border-dashed border-rule bg-card p-4 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0">
            <td colspan="8" class="block px-0 py-8 text-center text-sm text-ink-3 md:table-cell md:px-4 md:py-10">No support requests in this view.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-6">{{ $requests->links() }}</div>
</x-admin.layout>
