<x-admin.layout title="Data requests">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Data protection requests</h1>
      <p class="mt-1 text-sm text-ink-3">NDPA requests from sellers and buyers. Answer within one month.</p>
    </div>
    <div class="flex flex-wrap gap-2 text-xs font-semibold">
      @foreach (['open' => 'Open', 'overdue' => 'Overdue', 'pending' => 'Pending', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
        <a href="{{ route('admin.data-requests', ['status' => $value]) }}" class="rounded-lg px-3 py-2 {{ $activeStatus === $value ? 'bg-ink text-paper' : 'border border-rule bg-card text-ink-2' }}">{{ $label }} {{ $counts[$value] ?? 0 }}</a>
      @endforeach
    </div>
  </div>

  <div class="mt-6 space-y-3">
    @forelse ($requests as $dataRequest)
      @php $overdue = $dataRequest->resolved_at === null && $dataRequest->due_at !== null && $dataRequest->due_at->isPast(); @endphp
      <article class="rounded-2xl border {{ $overdue ? 'border-danger-text/40 bg-danger-text/5' : 'border-rule bg-card' }} p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-bold text-ink">{{ $dataRequest->name }} · {{ ucfirst($dataRequest->request_type) }}</p>
            <p class="mt-0.5 text-xs text-ink-3">{{ $dataRequest->email }}@if ($dataRequest->seller) · account #{{ $dataRequest->seller_id }}@endif</p>
            @if ($dataRequest->details)
              <p class="mt-2 max-w-2xl whitespace-pre-line text-xs text-ink-2">{{ $dataRequest->details }}</p>
            @endif
          </div>
          <div class="text-right text-[11px] text-ink-3">
            <p class="font-bold uppercase tracking-wide">{{ str_replace('_', ' ', $dataRequest->status) }}</p>
            <p class="mt-0.5">Received {{ $dataRequest->requested_at?->translatedFormat('j M Y') }}</p>
            <p class="mt-0.5 {{ $overdue ? 'font-bold text-danger-text' : '' }}">Due {{ $dataRequest->due_at?->translatedFormat('j M Y') ?? '—' }}</p>
            <p class="mt-0.5 {{ $dataRequest->verified_at ? 'text-brand-text' : '' }}">{{ $dataRequest->verified_at ? 'Identity verified' : 'Identity unverified' }}</p>
          </div>
        </div>

        <form method="POST" action="{{ route('admin.data-requests.update', $dataRequest) }}" class="mt-4 grid gap-3 border-t border-rule pt-4 sm:grid-cols-[10rem_1fr_auto] sm:items-end">
          @csrf
          <label class="block text-[11px] font-semibold text-ink-3">Status
            <select name="status" class="mt-1 w-full rounded-lg border border-rule bg-card px-3 py-2 text-xs text-ink">
              @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected($dataRequest->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
              @endforeach
            </select>
          </label>
          <label class="block text-[11px] font-semibold text-ink-3">What was done
            <input type="text" name="resolution_note" value="{{ $dataRequest->resolution_note }}" maxlength="2000" placeholder="Checked registered email, exported data…" class="mt-1 w-full rounded-lg border border-rule bg-card px-3 py-2 text-xs text-ink">
          </label>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 text-[11px] font-semibold text-ink-2">
              <input type="checkbox" name="verified" value="1" @checked($dataRequest->verified_at !== null)> Verified
            </label>
            <button class="rounded-lg bg-ink px-4 py-2 text-xs font-bold text-paper">Save</button>
          </div>
        </form>

        @if ($dataRequest->request_type === 'deletion' && $dataRequest->seller_id !== null && $dataRequest->verified_at !== null)
          <form method="POST" action="{{ route('admin.data-requests.purge', $dataRequest) }}" class="mt-3 flex flex-wrap items-center gap-3 border-t border-rule pt-3"
                onsubmit="return confirm('Permanently delete this seller\'s catalogue? This cannot be undone.');">
            @csrf
            <p class="text-[11px] text-ink-3">Deletion request, identity verified. This bypasses the 30-day recovery window.</p>
            <button class="rounded-lg border border-danger-text/40 px-3 py-2 text-[11px] font-bold text-danger-text hover:bg-danger-text/5">Permanently delete catalogue</button>
          </form>
        @endif
      </article>
    @empty
      <p class="rounded-2xl border border-rule bg-card p-6 text-sm text-ink-3">No requests match this filter.</p>
    @endforelse
  </div>
</x-admin.layout>
