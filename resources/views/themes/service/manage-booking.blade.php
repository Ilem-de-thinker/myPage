@php
    /**
        A booking somebody already made, looked up by reference and contact.

        The reference alone is not a secret — it is printed on a receipt and read
        out on WhatsApp — so the email or phone the booking was made with is
        required too. A wrong guess returns the same "we could not find that" as
        a reference that does not exist, which is what stops this page from being
        a way to enumerate other people's appointments.

        @var \App\Domains\Booking\Models\Booking|null $booking
    */
    use App\Domains\Booking\Enums\BookingStatus;
    use App\Shared\Support\Money;
    use App\Shared\Support\WhatsApp;

    $timezone = $page->settings['timezone'] ?? config('mypage.timezone');
    $deposit = $booking?->deposit_kobo ?? 0;
    $balance = $booking ? max(0, $booking->price_kobo - $deposit) : 0;
    $startsAt = $booking?->starts_at?->timezone($timezone);
    $endsAt = $booking?->ends_at?->timezone($timezone);
    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');
    $maps = $page->address ? 'https://maps.google.com/?q='.rawurlencode($page->address) : null;
@endphp

<x-themes.shared.layout :page="$page">

    <div class="shell py-12">

        {{-- Breadcrumbs --}}
        <nav class="flex items-center gap-2 text-xs text-[var(--ink-3)] mb-6" aria-label="Breadcrumb">
            <a href="{{ route('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)] transition-colors">Home</a>
            <span aria-hidden="true">/</span>
            <span class="text-[var(--ink)] font-semibold" aria-current="page">Manage Booking</span>
        </nav>

        {{-- Page Header --}}
        <div class="max-w-2xl mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold mb-4">
                <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                Client Self-Service Portal
            </span>
            <h1 class="t-display-1 text-[var(--ink)]">Track Appointment &amp; Retainer.</h1>
            <p class="t-body-lg text-[var(--ink-2)] mt-4">
                Look up your studio booking using your reference code and the WhatsApp phone or email you booked with. Check the session time, studio directions, and escrow retainer status.
            </p>
        </div>

        {{-- Lookup Bar. Hidden when the visitor arrived through their own signed link:
     the link already carries its authorisation, so the form is noise. --}}
        @if (! ($viaLink ?? false))
            <div class="card p-6 sm:p-8 mb-10">
            <form method="GET" action="{{ route('storefront.manage-booking', ['slug' => $page->slug]) }}"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="manage-ref" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Booking Reference *</label>
                    <input type="text"
                           id="manage-ref"
                           name="reference"
                           value="{{ $reference }}"
                           required
                           placeholder="e.g. BK-8941"
                           class="w-full h-11 px-3.5 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:ring-2 focus:ring-[var(--brand)] font-mono">
                </div>

                <div>
                    <label for="manage-contact" class="block text-xs font-bold text-[var(--ink)] mb-1.5">WhatsApp Phone or Email *</label>
                    <input type="text"
                           id="manage-contact"
                           name="contact"
                           value="{{ $contact }}"
                           required
                           placeholder="e.g. 0803 123 4567"
                           class="w-full h-11 px-3.5 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:ring-2 focus:ring-[var(--brand)]">
                </div>

                <div class="sm:col-span-2 sm:self-end">
                    <button type="submit"
                            class="w-full sm:w-auto h-11 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs shadow-sm hover:opacity-95 transition-opacity flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <span>Search Booking</span>
                    </button>
                </div>
            </form>
            </div>
        @endif

        @if ($searched && $booking === null)
            <div class="card p-8 text-center max-w-xl mx-auto border-2 border-dashed border-[var(--rule)]">
                <h2 class="t-title text-[var(--ink)] mb-2">We could not find that booking.</h2>
                <p class="t-body text-xs text-[var(--ink-2)] leading-relaxed">
                    Check the reference and the contact you booked with. If both are right and it still
                    will not load, message the studio on WhatsApp and they will confirm from their end.
                </p>
            </div>
        @elseif ($booking)
            <div class="space-y-8">

                {{-- Result Card --}}
                <div class="card p-6 sm:p-8 border-[var(--brand-edge)] shadow-sm space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--rule)] pb-6">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-sm font-extrabold text-[var(--ink)]">Ref: {{ $booking->reference }}</span>
                                <span @class([
                                    'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-[var(--radius-pill)] border text-[10px] font-bold',
                                    'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20' => $booking->status === BookingStatus::Confirmed,
                                    'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20' => $booking->status === BookingStatus::PendingPayment,
                                    'bg-[var(--surface)] text-[var(--ink-2)] border-[var(--rule)]' => ! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::PendingPayment], true),
                                ])>
                                    <span class="w-1.5 h-1.5 rounded-full" aria-hidden="true"
                                          style="background: {{ $booking->status === BookingStatus::Confirmed ? 'var(--brand)' : 'currentColor' }}"></span>
                                    {{ $booking->status->label() }}
                                </span>
                            </div>
                            <h2 class="t-title text-[var(--ink)] mt-2">{{ $booking->service?->name ?? 'Appointment' }}</h2>
                            @if ($booking->customer_name)
                                <p class="t-meta mt-1">Booked by {{ $booking->customer_name }}</p>
                            @endif
                        </div>

                        @if ($startsAt)
                            <div class="sm:text-right">
                                <span class="text-[10px] text-[var(--ink-3)] block uppercase tracking-wider">Scheduled Session</span>
                                <p class="text-sm font-bold text-[var(--ink)]">{{ $startsAt->format('D, d M Y') }}</p>
                                <p class="text-xs font-semibold text-[var(--brand-text)]">
                                    {{ $startsAt->format('g:i A') }}@if ($endsAt) – {{ $endsAt->format('g:i A') }}@endif
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Key Details Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                        <div class="space-y-1 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
                            <span class="text-[var(--ink-3)] block">Assigned Artist</span>
                            <p class="font-bold text-[var(--ink)]">{{ $booking->staffMember?->name ?? 'Assigned on arrival' }}</p>
                            @if ($booking->service?->durationLabel())
                                <p class="t-meta">{{ $booking->service->durationLabel() }}</p>
                            @endif
                        </div>

                        <div class="space-y-1 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
                            <span class="text-[var(--ink-3)] block">Studio Location</span>
                            <p class="font-bold text-[var(--ink)]">{{ $page->city ?: $page->name }}</p>
                            @if ($where)
                                <p class="t-meta">{{ $where }}</p>
                            @endif
                        </div>

                        <div class="space-y-1 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
                            <span class="text-[var(--ink-3)] block">Arrival Window</span>
                            @if ($startsAt)
                                <p class="font-bold text-[var(--brand-text)]">{{ $startsAt->copy()->subMinutes(15)->format('g:i A') }} (15 min prep)</p>
                            @else
                                <p class="font-bold text-[var(--ink)]">—</p>
                            @endif
                            <p class="t-meta">Arrive early so there is time to settle in</p>
                        </div>
                    </div>

                    {{-- Escrow Vault Breakdown --}}
                    <div class="p-6 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] space-y-4">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <span class="inline-flex items-center gap-2 text-xs font-bold text-[var(--brand-text)]">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>mypage Sovereign Escrow Vault Status</span>
                            </span>
                            <span class="font-mono text-xs font-bold text-[var(--brand-text)]">Vault Token #{{ str($booking->reference)->after('-') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 text-xs">
                            <div>
                                <span class="text-[var(--ink-3)] block">Total Package Value</span>
                                <p class="t-price-sm">{{ Money::format($booking->price_kobo) }}</p>
                            </div>
                            <div>
                                <span class="text-[var(--ink-3)] block">Retainer Held in Escrow</span>
                                <p @class([
                                    't-price-sm',
                                    'text-emerald-600 dark:text-emerald-400' => $deposit > 0 && $booking->status === BookingStatus::Confirmed,
                                ])>
                                    {{ $deposit > 0 ? Money::format($deposit).' (Locked)' : 'No deposit required' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-[var(--ink-3)] block">Balance Payable at the Studio</span>
                                <p class="t-price-sm text-[var(--ink)]">{{ Money::format($balance) }}</p>
                            </div>
                        </div>

                        <p class="text-[11px] text-[var(--ink-2)] border-t border-[var(--brand-edge)] pt-3 leading-relaxed">
                            @if ($deposit > 0)
                                Your {{ Money::format($deposit) }} retainer is held securely in the sovereign escrow vault. It is only released to {{ $page->name }} once the appointment is completed and you approve the result.
                            @else
                                This booking carries no deposit, so there is nothing held in escrow. The full amount is settled at the studio.
                            @endif
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                        <div class="flex flex-wrap items-center gap-3">
                            @if ($maps)
                                <a href="{{ $maps }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="px-4 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs font-bold hover:bg-[var(--surface)] transition-colors flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-[var(--brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span>Directions in Google Maps</span>
                                </a>
                            @endif

                            @php $desk = WhatsApp::link($page, 'Hello '.$page->name.', I am inquiring about my booking '.$booking->reference.'.'); @endphp
                            @if ($desk)
                                <a href="{{ $desk }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="px-4 py-2.5 rounded-[var(--radius)] bg-[#25D366] text-white text-xs font-bold hover:opacity-95 transition-opacity flex items-center gap-1.5 shadow-sm">
                                    <x-ui.whatsapp-mark />
                                    <span>Studio WhatsApp Desk</span>
                                </a>
                            @endif
                        </div>

                        <a href="{{ route('storefront.book', ['slug' => $page->slug, 'booking' => $booking->reference]) }}"
                           class="text-xs text-[var(--ink-3)] hover:text-[var(--ink)] underline">
                            Need to reschedule or modify?
                        </a>
                    </div>
                </div>

                {{-- Preparation & Etiquette Guide --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="card p-6 space-y-3">
                        <h3 class="text-sm font-bold text-[var(--ink)]">Before You Arrive</h3>
                        <ul class="space-y-2 text-[var(--ink-2)]">
                            <li class="flex items-start gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
                                <span>Arrive fifteen minutes early. A late arrival shortens the session rather than delaying the next client.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
                                <span>Bring anything the session depends on — fabric, ornaments, references — so nothing has to be improvised on the day.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="card p-6 space-y-3">
                        <h3 class="text-sm font-bold text-[var(--ink)]">At the Studio</h3>
                        <ul class="space-y-2 text-[var(--ink-2)]">
                            <li class="flex items-start gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
                                <span>{{ $where ? 'Find us at '.$where.'.' : 'Message the WhatsApp desk for directions before you travel.' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
                                <span>Your retainer is released only once you have approved the result, so say so if something is not right.</span>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        @endif

    </div>

</x-themes.shared.layout>