@extends('layouts.app')

@section('content')
    @php $timezone = $page->settings['timezone'] ?? 'Africa/Lagos'; @endphp

    <x-ui.page-header
        :title="$booking->customer_name ?: 'Guest'"
        :subtitle="$booking->reference.' · '.($booking->service?->name ?? 'Service removed')"
        :back="route('bookings.index')"
        back-label="Back to bookings"
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        <div class="bg-card rounded-2xl border border-rule p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] uppercase tracking-wide text-ink-3">Status</p>
                    <span class="mt-1 inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $booking->status->badgeClasses() }}">
                        {{ $booking->status->label() }}
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-[11px] uppercase tracking-wide text-ink-3">Price</p>
                    <p class="mt-1 text-xl font-extrabold text-ink">{{ \App\Shared\Support\Money::format($booking->price_kobo) }}</p>
                    @if ($booking->deposit_kobo > 0)
                        <p class="text-[11px] text-ink-3">{{ \App\Shared\Support\Money::format($booking->deposit_kobo) }} paid to book</p>
                    @endif
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-rule pt-5 text-xs">
                <div>
                    <dt class="text-ink-3">Starts</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $booking->starts_at->timezone($timezone)->format('D j M Y, g:i a') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-3">Ends</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $booking->ends_at->timezone($timezone)->format('D j M Y, g:i a') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-3">With</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $booking->staffMember?->name ?? 'Anyone free' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-3">Phone</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $booking->customer_phone ?: '—' }}</dd>
                </div>
                @if ($booking->customer_email)
                    <div class="col-span-2">
                        <dt class="text-ink-3">Email</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $booking->customer_email }}</dd>
                    </div>
                @endif
                @if ($booking->notes)
                    <div class="col-span-2">
                        <dt class="text-ink-3">Their note</dt>
                        <dd class="mt-0.5 font-semibold text-ink whitespace-pre-line">{{ $booking->notes }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @php $next = $booking->status->allowedTransitions(); @endphp

        @if ($next)
            <div class="bg-card rounded-2xl border border-rule p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">Move to</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($next as $status)
                        <form method="POST" action="{{ route('bookings.status', $booking) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $status->value }}">
                            <button class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-hover">
                                {{ $status->label() }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        @if (in_array($booking->status, [App\Domains\Booking\Enums\BookingStatus::Confirmed, App\Domains\Booking\Enums\BookingStatus::PendingPayment], true))
            <div class="bg-card rounded-2xl border border-rule p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">Change the time</h2>
                <p class="text-xs text-ink-3 mb-3">
                    Moves the booking and frees the old slot. Inside your cancellation window this asks you to
                    ring the customer instead.
                </p>
                <a href="{{ route('bookings.reschedule.show', $booking) }}"
                   class="rounded-xl border border-rule px-4 py-2 text-xs font-semibold text-ink hover:bg-surface">
                    Reschedule
                </a>
            </div>
        @endif

        @if ($booking->history->isNotEmpty())
            <div class="bg-card rounded-2xl border border-rule p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">History</h2>
                <ul class="space-y-2 text-xs">
                    @foreach ($booking->history as $entry)
                        <li class="flex items-center justify-between gap-4">
                            <span class="text-ink-2">
                                {{ $entry->from_status?->label() ?? 'Created' }} &rarr;
                                <span class="font-semibold text-ink">{{ $entry->to_status->label() }}</span>
                            </span>
                            <span class="shrink-0 text-ink-3">{{ $entry->created_at->format('M d, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection