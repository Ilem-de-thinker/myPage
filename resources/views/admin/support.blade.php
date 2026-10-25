<x-admin.layout title="Support">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Support queue</h1>
      <p class="mt-1 text-sm text-ink-3">Buyer questions and seller requests sent to mypage.ng.</p>
    </div>
    <div class="flex flex-wrap gap-2 text-xs font-semibold">
      @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $value => $label)
        <a href="{{ route('admin.support', ['status' => $value, 'source' => $activeSource]) }}" class="rounded-lg px-3 py-2 {{ $activeStatus === $value ? 'bg-ink text-white' : 'border border-rule bg-card text-ink-2' }}">{{ $label }}{{ $value !== 'all' ? ' '.($counts[$value] ?? 0) : '' }}</a>
      @endforeach
    </div>
  </div>

  <div class="mt-5 flex gap-2 text-xs font-semibold">
    @foreach (['' => 'All', 'buyer' => 'Platform buyers', 'store' => 'Store buyers', 'seller' => 'Sellers'] as $value => $label)
      <a href="{{ route('admin.support', ['status' => $activeStatus, 'source' => $value]) }}" class="rounded-lg border border-rule px-3 py-2 {{ $activeSource === $value || ($value === '' && ! $activeSource) ? 'bg-tint text-ink' : 'bg-card text-ink-2' }}">{{ $label }}</a>
    @endforeach
  </div>

  <div class="mt-6 space-y-4">
    @forelse ($requests as $supportRequest)
      <a href="{{ route('admin.support.show', $supportRequest) }}" class="block rounded-2xl border border-rule bg-card p-5 transition hover:border-brand-edge hover:bg-tint">
        <div class="flex flex-col justify-between gap-3 sm:flex-row">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full bg-tint px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-ink-2">{{ $supportRequest->source }}</span>
              <span class="text-xs font-bold uppercase tracking-wide text-ink-3">{{ str_replace('_', ' ', $supportRequest->category) }}</span>
            </div>
            <h2 class="mt-2 text-base font-bold text-ink">{{ $supportRequest->subject }}</h2>
            <p class="mt-1 text-[11px] font-bold tracking-wide text-brand">{{ $supportRequest->ticket_number }}</p>
            <p class="mt-1 text-xs text-ink-3">{{ $supportRequest->requester_name ?: ($supportRequest->seller?->name ?? 'Anonymous') }} · {{ $supportRequest->requester_contact ?: 'No contact supplied' }} · {{ $supportRequest->created_at?->format('j M Y, g:ia') }}</p>
            @if ($supportRequest->storePage)
              <p class="mt-1 text-xs text-ink-3">Store: {{ $supportRequest->storePage->name }}</p>
            @endif
          </div>
          <span class="h-fit rounded-full bg-tint px-3 py-1 text-xs font-semibold text-ink-2">{{ $supportRequest->status->label() }}</span>
        </div>
        <p class="mt-4 text-xs text-brand">{{ $supportRequest->messages->count() }} conversation {{ $supportRequest->messages->count() === 1 ? 'message' : 'messages' }} · Open ticket →</p>
      </a>
    @empty
      <div class="rounded-2xl border border-dashed border-rule bg-card p-10 text-center text-sm text-ink-3">No support requests in this view.</div>
    @endforelse
  </div>

  <div class="mt-6">{{ $requests->links() }}</div>
</x-admin.layout>
