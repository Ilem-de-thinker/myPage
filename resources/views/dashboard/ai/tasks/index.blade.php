@extends('layouts.app')

@section('heading', 'AI task history')

@section('content')
    <div class="p-6 space-y-4">
        <h2 class="text-lg font-bold">Past tasks</h2>
        @if ($tasks->isEmpty())
            <p class="text-ink-3">No tasks yet.</p>
        @else
            <ul class="space-y-2">
                @foreach ($tasks as $task)
                    <li class="rounded-xl border border-rule bg-card p-4 flex items-center justify-between">
                        <div>
                            <p class="font-semibold">{{ ucfirst($task->type) }}</p>
                            <p class="text-xs text-ink-3">{{ $task->status }} · {{ $task->completed }}/{{ $task->total }} done · {{ $task->failed }} failed</p>
                        </div>
                        <a href="{{ route('dashboard.ai.tasks.show', $task) }}" class="text-sm text-brand">View</a>
                    </li>
                @endforeach
            </ul>
            {{ $tasks->links() }}
        @endif
    </div>
@endsection
