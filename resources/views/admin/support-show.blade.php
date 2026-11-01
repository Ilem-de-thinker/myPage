<x-admin.layout title="Support ticket">
  <div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.support') }}" class="text-xs font-semibold text-ink-3 hover:text-ink">← Back to support queue</a>

    <div class="mt-5 flex flex-col justify-between gap-4 border-b border-rule pb-5 sm:flex-row sm:items-end">
      <div>
        <p class="text-[11px] font-bold tracking-wide text-brand-text">{{ $supportRequest->ticket_number }}</p>
        <h1 class="mt-1 font-display text-2xl font-extrabold tracking-tight">{{ $supportRequest->subject }}</h1>
        <p class="mt-1 text-xs text-ink-3">{{ ucfirst($supportRequest->source) }} · {{ $supportRequest->requester_name ?: ($supportRequest->seller?->name ?? 'Anonymous') }} · {{ $supportRequest->requester_contact ?: 'No contact supplied' }}</p>
      </div>
      <span class="rounded-full bg-tint px-3 py-1 text-xs font-semibold text-ink-2">{{ $supportRequest->status->label() }}</span>
    </div>

    <div class="mt-6 space-y-3">
      @forelse ($supportRequest->messages as $message)
        <article class="rounded-2xl border border-rule {{ $message->sender_type === 'admin' ? 'bg-brand-wash' : 'bg-card' }} p-5">
          <div class="flex items-center justify-between gap-3 text-[10px] font-semibold text-ink-3">
            <span>{{ ucfirst($message->sender_type) }}</span>
            <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('j M Y, g:ia') }}</time>
          </div>
          <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-2">{{ $message->body }}</p>
        </article>
      @empty
        <article class="rounded-2xl border border-rule bg-card p-5 text-sm leading-6 text-ink-2">{{ $supportRequest->body }}</article>
      @endforelse
    </div>

    <form method="POST" action="{{ route('admin.support.update', $supportRequest) }}" class="mt-6 grid gap-4 rounded-2xl border border-rule bg-surface p-5 sm:grid-cols-[auto_1fr_auto] sm:items-end">
      @csrf
      <label class="text-xs font-semibold text-ink-2">Status
        <select name="status" class="mt-1 rounded-lg border border-rule bg-paper px-3 py-2 text-xs text-ink">
          @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)
            <option value="{{ $value }}" @selected($supportRequest->status->value === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-xs font-semibold text-ink-2">Reply to ticket
        <input name="reply" maxlength="4000" class="mt-1 w-full rounded-lg border border-rule bg-paper px-3 py-2 text-xs text-ink placeholder:text-ink-3" placeholder="Write a reply">
      </label>
      <button class="rounded-lg bg-brand px-4 py-2 text-xs font-bold text-brand-ink">Save ticket</button>
    </form>
  </div>
</x-admin.layout>
