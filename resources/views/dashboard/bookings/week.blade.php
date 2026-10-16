@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Bookings"
        subtitle="Your week at a glance."
    >
        <a href="{{ route('bookings.index', ['view' => 'week']) }}"
           class="rounded-xl bg-brand px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
            Week
        </a>
        <a href="{{ route('bookings.index') }}"
           class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface">
            List
        </a>
    </x-ui.page-header>

    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('bookings.index', ['view' => 'week', 'week' => $previousWeek]) }}"
           class="text-xs font-semibold text-ink-3 hover:text-ink">&larr; Previous week</a>

        <a href="{{ route('bookings.index', ['view' => 'week']) }}"
           class="text-xs font-semibold text-brand hover:text-brand-text">
            This week
        </a>

        <a href="{{ route('bookings.index', ['view' => 'week', 'week' => $nextWeek]) }}"
           class="text-xs font-semibold text-ink-3 hover:text-ink">Next week &rarr;</a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3">
        @foreach ($days as $day)
            <div @class([
                'rounded-2xl border bg-card p-3 min-h-32',
                'border-brand' => $day['isToday'],
                'border-rule' => ! $day['isToday'],
            ])>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-3">
                    {{ $day['label'] }}
                </p>

                @forelse ($day['bookings'] as $booking)
                    <a href="{{ route('bookings.show', $booking) }}"
                       class="mt-2 block rounded-xl border border-rule bg-surface p-2 hover:border-rule-strong">
                        <span class="block text-xs font-bold text-ink">
                            {{ $booking->starts_at->timezone($timezone)->format('g:i A') }}
                        </span>
                        <span class="mt-0.5 block truncate text-[11px] text-ink-2">
                            {{ $booking->customer_name ?: 'Guest' }}
                        </span>
                        <span class="mt-0.5 block truncate text-[10px] text-ink-3">
                            {{ $booking->service?->name ?? 'Service removed' }}
                        </span>
                        <span class="mt-1 inline-block rounded-full px-1.5 py-0.5 text-[9px] font-semibold {{ $booking->status->badgeClasses() }}">
                            {{ $booking->status->label() }}
                        </span>
                    </a>
                @empty
                    <p class="mt-3 text-[11px] text-ink-3">Nothing</p>
                @endforelse
            </div>
        @endforeach
    </div>
@endsection