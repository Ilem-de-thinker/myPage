@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Customer chat" subtitle="Orders, bookings, and enquiries in one inbox." />
    <div x-data="chatInbox({unread: {{ $threads->sum('unread_count') }}, refresh: @jsExpr(route('dashboard.chat.refresh'))})" class="mx-auto max-w-4xl space-y-3">
        <div class="flex items-center justify-between rounded-2xl border border-brand-edge bg-brand-wash px-4 py-3 text-xs text-brand-text"><span>Unread customer messages</span><span class="rounded-full bg-brand px-2 py-1 font-bold text-brand-ink" x-text="unread">{{ $threads->sum('unread_count') }}</span></div>
        @forelse ($threads as $thread)
            <a href="{{ route('dashboard.chat.show', $thread->id) }}" class="block rounded-2xl border border-rule bg-card p-5 transition hover:border-brand-edge hover:bg-tint">
                <div class="flex items-center justify-between gap-4"><h2 class="text-sm font-bold text-ink">{{ $thread->subject ?: ($thread->guest_name ?: 'Customer conversation') }}</h2><div class="flex items-center gap-3"><span class="text-[11px] text-ink-3">{{ $thread->last_message_at?->diffForHumans() }}</span>@if ($thread->unread_count)<span class="rounded-full bg-brand px-2 py-1 text-[10px] font-bold text-brand-ink">{{ $thread->unread_count }}</span>@endif</div></div>
                <p class="mt-2 text-xs text-ink-2">{{ $thread->messages_count }} messages · {{ ucfirst($thread->status) }}</p>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-rule bg-card p-10 text-center text-sm text-ink-2">Customer conversations will appear here.</div>
        @endforelse
        {{ $threads->links() }}
    </div>
@endsection
