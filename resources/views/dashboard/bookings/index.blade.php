@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Bookings" subtitle="Who is coming, and when.">
        <a href="{{ route('bookings.index', ['view' => 'week']) }}"
           class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface">
            Week view
        </a>
    </x-ui.page-header>

    <div class="mb-4 flex gap-2 border-b border-rule">
        @foreach (['today' => 'Today', 'upcoming' => 'Upcoming', 'past' => 'Past'] as $value => $label)
            <a href="{{ route('bookings.index', ['filter' => $value]) }}"
               @class([
                   '-mb-px border-b-2 px-4 py-2.5 text-xs font-semibold transition',
                   'border-brand text-brand' => $activeFilter === $value,
                   'border-transparent text-ink-3 hover:text-ink' => $activeFilter !== $value,
               ])>
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-card rounded-2xl border border-rule overflow-hidden">
        @forelse ($bookings as $booking)
            <div class="flex flex-wrap items-center gap-3 border-b border-rule px-4 py-3 last:border-0 hover:bg-surface/60">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('bookings.show', $booking) }}" class="text-sm font-semibold text-ink hover:text-brand">
                            {{ $booking->customer_name ?: 'Guest' }}
                        </a>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $booking->status->badgeClasses() }}">
                            {{ $booking->status->label() }}
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-ink-3">
                        {{ $booking->service?->name ?? 'Service removed' }}
                        @if ($booking->staffMember)
                            &middot; with {{ $booking->staffMember->name }}
                        @endif
                    </p>
                </div>

                <p class="text-xs font-semibold text-ink">
                    {{ $booking->starts_at->timezone($timezone)->format('D j M, g:i a') }}
                </p>

                <a href="{{ route('bookings.show', $booking) }}" class="text-xs font-semibold text-brand">View</a>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm text-ink-3 mb-1">
                    {{ $activeFilter === 'today' ? 'Nothing booked for today.' : "No {$activeFilter} bookings." }}
                </p>
                <p class="text-xs text-ink-3">
                    Bookings arrive here the moment a customer picks a slot on your page.
                </p>
            </div>
        @endforelse
    </div>

    @if ($bookings->hasPages())
        <div class="mt-4">{{ $bookings->links() }}</div>
    @endif
@endsection