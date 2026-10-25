<x-admin.layout title="Store reports">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <div>
      <h1 class="font-display text-2xl font-extrabold tracking-tight">Store reports</h1>
      <p class="mt-1 text-sm text-ink-3">Moderation input from buyers. This queue does not adjudicate or move money.</p>
    </div>
    <div class="flex gap-2 text-xs font-semibold">
      @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $value => $label)
        <a href="{{ route('admin.reports', ['status' => $value]) }}" class="rounded-lg px-3 py-2 {{ $activeStatus === $value ? 'bg-ink text-white' : 'border border-rule bg-card text-ink-2' }}">{{ $label }}{{ $value !== 'all' ? ' '.($counts[$value] ?? 0) : '' }}</a>
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

  <div class="mt-6 space-y-4">
    @forelse ($reports as $report)
      <article class="rounded-2xl border border-rule bg-card p-5">
        <div class="flex flex-col justify-between gap-3 sm:flex-row">
          <div>
            <p class="text-xs font-bold uppercase tracking-wide text-ink-3">{{ str_replace('_', ' ', $report->category) }}</p>
            <h2 class="mt-1 text-base font-bold text-ink">{{ $report->storePage->name }}</h2>
            <p class="mt-1 text-xs text-ink-3">{{ $report->created_at?->format('j M Y, g:ia') }} · {{ $report->status->label() }}</p>
          </div>
          <span class="h-fit rounded-full bg-tint px-3 py-1 text-xs font-semibold text-ink-2">{{ $report->status->label() }}</span>
        </div>
        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-ink-2">{{ $report->body }}</p>
        @if ($report->reporter_name || $report->reporter_contact)
          <p class="mt-3 text-xs text-ink-3">Reporter: {{ $report->reporter_name ?: 'Anonymous' }}{{ $report->reporter_contact ? ' · '.$report->reporter_contact : '' }}</p>
        @endif
        <form method="POST" action="{{ route('admin.reports.update', $report) }}" class="mt-4 grid gap-3 border-t border-rule pt-4 sm:grid-cols-[auto_1fr_auto] sm:items-end">
          @csrf
          <div>
            <label for="status-{{ $report->id }}" class="block text-xs font-semibold text-ink-2">Decision</label>
            <select id="status-{{ $report->id }}" name="status" class="mt-1 rounded-lg border border-rule bg-paper px-3 py-2 text-xs text-ink">
              @foreach (['open' => 'Open', 'resolved' => 'Resolve', 'dismissed' => 'Dismiss'] as $value => $label)
                <option value="{{ $value }}" @selected($report->status->value === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="note-{{ $report->id }}" class="block text-xs font-semibold text-ink-2">Internal note</label>
            <input id="note-{{ $report->id }}" name="admin_note" value="{{ $report->admin_note }}" maxlength="2000" class="mt-1 w-full rounded-lg border border-rule bg-paper px-3 py-2 text-xs text-ink">
          </div>
          <button class="rounded-lg bg-ink px-4 py-2 text-xs font-bold text-white">Save</button>
        </form>
      </article>
    @empty
      <div class="rounded-2xl border border-dashed border-rule bg-card p-10 text-center text-sm text-ink-3">No reports in this view.</div>
    @endforelse
  </div>

  <div class="mt-6">{{ $reports->links() }}</div>
</x-admin.layout>
