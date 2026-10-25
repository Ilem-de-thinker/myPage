@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('support.index') }}" class="text-xs font-semibold text-ink-3 hover:text-ink">← Back to tickets</a>

        <div class="mt-5 flex flex-col justify-between gap-3 border-b border-rule pb-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] font-bold tracking-wide text-brand">{{ $supportRequest->ticket_number }}</p>
                <h1 class="mt-1 text-xl font-bold text-ink">{{ $supportRequest->subject }}</h1>
                <p class="mt-1 text-xs text-ink-3">{{ ucfirst($supportRequest->source) }} · {{ str_replace('_', ' ', $supportRequest->category) }}</p>
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

        <form method="POST" action="{{ route('support.reply', $supportRequest) }}" class="mt-6 rounded-2xl border border-rule bg-surface p-5">
            @csrf
            <label class="block text-xs font-semibold text-ink">Reply to this ticket
                <textarea name="body" required minlength="2" maxlength="4000" rows="5" class="mt-2 w-full rounded-xl border border-rule-strong bg-card p-3 text-sm text-ink placeholder:text-ink-3" placeholder="Add information or reply to support."></textarea>
            </label>
            <button class="mt-3 rounded-xl bg-brand px-4 py-3 text-xs font-bold text-brand-ink">Send reply</button>
        </form>
    </div>
@endsection
