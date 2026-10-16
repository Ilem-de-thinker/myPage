@extends('layouts.app')

@section('content')
    @php $timezone = $page->settings['timezone'] ?? 'Africa/Lagos'; @endphp

    <x-ui.page-header
        title="Move this booking"
        :subtitle="$booking->reference.' · '.($booking->service?->name ?? 'Service removed')"
        :back="route('bookings.show', $booking)"
        back-label="Back to the booking"
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        @if ($locked)
            <div class="rounded-2xl border border-warn-edge bg-warn-wash p-5">
                <p class="text-sm font-semibold text-warn-text">Too late to move this online.</p>
                <p class="mt-1 text-xs text-warn-text">
                    It starts in {{ $hoursUntil }} hour{{ $hoursUntil === 1 ? '' : 's' }} and your cancellation
                    window is {{ $windowHours }} hours. Ring the customer on
                    @if ($booking->customer_phone){{ $booking->customer_phone }} @else their number on file @endif
                    and agree a new time, then move it here yourself.
                </p>
            </div>
        @else
            <form method="GET" action="{{ route('bookings.reschedule.show', $booking) }}" class="rounded-2xl border border-rule bg-card p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Day</span>
                        <select name="date" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                            <option value="">Choose a day…</option>
                            @foreach ($dates as $date)
                                <option value="{{ $date['value'] }}" @selected($selectedDate === $date['value'])>{{ $date['day'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($staff->isNotEmpty())
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">Who</span>
                            <select name="staff" onchange="this.form.submit()"
                                    class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                                <option value="">Anyone free</option>
                                @foreach ($staff as $member)
                                    <option value="{{ $member->id }}" @selected((string) $selectedStaff === (string) $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>

                <noscript><button class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white">Show times</button></noscript>
            </form>

            @if ($errors->any())
                <div class="rounded-2xl border border-danger-edge bg-danger-wash p-5">
                    @foreach ($errors->all() as $error)
                        <p class="text-xs text-danger-text">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('bookings.reschedule.store', $booking) }}" class="rounded-2xl border border-rule bg-card p-5">
                @csrf
                <input type="hidden" name="starts_at" id="reschedule-starts-at" value="{{ old('starts_at') }}">

                <p class="text-xs font-semibold text-ink mb-3">Pick a new time</p>

                @if ($slots->isEmpty())
                    <p class="text-xs text-ink-3">
                        Choose a day above to see what is free.
                    </p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($slots as $slot)
                            <button type="button"
                                    class="rounded-xl border-2 border-rule px-4 py-2 text-xs font-medium text-ink hover:border-ink-3"
                                    onclick="document.getElementById('reschedule-starts-at').value = @js($slot['starts_at']); this.parentElement.querySelectorAll('button').forEach(b => { b.className = 'rounded-xl border-2 border-rule px-4 py-2 text-xs font-medium text-ink hover:border-ink-3'; }); this.className = 'rounded-xl border-2 border-[var(--brand)] bg-[var(--brand)] text-white px-4 py-2 text-xs font-semibold';">
                                {{ $slot['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5">
                    <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover"
                            onclick="const v = document.getElementById('reschedule-starts-at').value; if (!v) { event.preventDefault(); alert('Pick a new time first.'); }">
                        Move the booking
                    </button>
                </div>
            </form>
        @endif
    </div>
@endsection