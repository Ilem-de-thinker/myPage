@extends('layouts.app')

@section('heading', 'Notifications')

@section('content')
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">All notifications</h2>
            <form method="POST" action="{{ route('notifications.read') }}">
                @csrf
                <button class="min-h-9 rounded-lg border border-rule px-3 text-xs font-bold text-ink-2">Mark all as read</button>
            </form>
        </div>

        @if ($notifications->isEmpty())
            <p class="text-ink-3">No notifications yet.</p>
        @else
            <ul class="space-y-2">
                @foreach ($notifications as $notification)
                    @php $notice = $notification->data; @endphp
                    <li class="rounded-xl border border-rule bg-card p-4 {{ $notification->read_at ? '' : 'border-brand-edge' }}">
                        <p class="text-sm font-bold text-ink">{{ $notice['title'] ?? 'Account notice' }}</p>
                        <p class="mt-1 text-xs text-ink-2">{{ $notice['body'] ?? '' }}</p>
                        @if (! empty($notice['url']))
                            <a href="{{ $notice['url'] }}" class="mt-2 inline-block text-xs font-bold text-brand">{{ $notice['action'] ?? 'Review' }}</a>
                        @endif
                        <p class="mt-2 text-[10px] text-ink-3">{{ $notification->created_at->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ul>
            {{ $notifications->links() }}
        @endif
    </div>
@endsection