@props(['page', 'booking'])

@php
    use App\Shared\Support\Money;
    use App\Shared\Support\StoreUrl;
    use App\Shared\Support\WhatsApp;
    use Illuminate\Support\Carbon;

    $timezone = $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');
    $zoneLabel = Carbon::now($timezone)->format('T');

    // The address, once: the studio card and the maps link quote the same thing.
    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');
    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::homePath($page);

    // Only a real booking carries these. Every figure below is read off the row,
    // so a booking with no deposit prints "Paid in full" rather than a zero.
    $service = $booking?->service;
    $totalKobo = (int) ($booking?->price_kobo ?? 0);
    $depositKobo = (int) ($booking?->deposit_kobo ?? 0);
    $balanceKobo = max(0, $totalKobo - $depositKobo);

    $desk = $booking
        ? WhatsApp::link($page, 'Hello '.$page->name.', I am inquiring about my booking '.$booking->reference.'.')
        : null;
@endphp

<div class="shell py-12">

  {{-- Breadcrumbs --}}
  <nav class="flex items-center gap-2 text-xs text-[var(--ink-3)] mb-6" aria-label="Breadcrumb">
    <a href="{{ $home }}" class="hover:text-[var(--ink)] transition-colors">Home</a>
    <span aria-hidden="true">/</span>
    <span class="text-[var(--ink)] font-semibold" aria-current="page">Manage Booking</span>
  </nav>

  {{-- Page header --}}
  <div class="max-w-2xl mb-10">
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold mb-4">
      <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
      Client Self-Service Portal
    </span>

    <h1 class="t-display-1 text-[var(--ink)]">Track Appointment &amp; Retainer.</h1>

    <p class="t-body-lg text-[var(--ink-2)] mt-4">
      Look up your booking with the reference from your confirmation. Check the schedule, the address, and what you have paid.
    </p>
  </div>

  {{--
    The lookup. A plain GET form onto the page's own confirmation route, so a
    reference typed here is looked up by the server rather than filtered on the
    page by a piece of state the visitor could have got wrong.
  --}}
  <div class="card p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] mb-10">
    <form method="GET" action="{{ \App\Shared\Support\StoreUrl::path('storefront.booking-confirmation', $storefront) }}"
          class="flex flex-col sm:flex-row gap-4">
      <div class="flex-1">
        <label for="track-booking-ref" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Booking Reference *</label>
        <input type="text"
               id="track-booking-ref"
               name="booking"
               value="{{ request('booking') }}"
               required
               placeholder="e.g. BK7F2K9QX"
               class="w-full h-11 px-3.5 text-xs rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:ring-2 focus:ring-[var(--brand)] font-mono">
      </div>

      <div class="sm:self-end">
        <button type="submit"
                class="w-full sm:w-auto h-11 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs shadow-sm hover:opacity-95 transition-opacity flex items-center justify-center gap-2">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <span>Search Booking</span>
        </button>
      </div>
    </form>
  </div>

  @if (! $booking)
    {{-- No reference in the URL. Ask for one rather than showing an empty card. --}}
    <div class="card p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] text-center">
      <p class="t-title text-[var(--ink)]">Enter your booking reference above.</p>
      <p class="t-body mt-2 text-[var(--ink-2)]">
        It is on the confirmation {{ $page->name }} sent you. If you cannot find it, the studio desk can look it up from your WhatsApp number.
      </p>
    </div>
  @else
    {{-- Result card --}}
    <div class="card p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--brand-edge)] shadow-sm space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--rule)] pb-6">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-sm font-extrabold text-[var(--ink)]">Ref: {{ $booking->reference }}</span>

            @php
                // Green once the slot is genuinely the buyer's; amber while it is
                // only being held against payment.
                $secured = in_array($booking->status, [
                    \App\Domains\Booking\Enums\BookingStatus::Confirmed,
                    \App\Domains\Booking\Enums\BookingStatus::Completed,
                ], true);
            @endphp
            <span @class([
                'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-[var(--radius-pill)] text-[10px] font-bold border',
                'bg-[var(--ok-wash)] text-[var(--ok-text)] border-[var(--ok-edge)]' => $secured,
                'bg-[var(--warn-wash)] text-[var(--warn-text)] border-[var(--warn-edge)]' => ! $secured,
            ])>
              <span @class([
                  'w-1.5 h-1.5 rounded-full',
                  'bg-[var(--ok-text)]' => $secured,
                  'bg-[var(--warn-text)]' => ! $secured,
              ]) aria-hidden="true"></span>
              {{ $booking->status->label() }}{{ $secured ? ' & Studio Time Reserved' : '' }}
            </span>
          </div>

          <h2 class="t-title text-[var(--ink)] mt-2">{{ $service?->name ?? 'Your appointment' }}</h2>

          @if ($booking->customer_name)
            <p class="t-meta mt-1">Booked by {{ $booking->customer_name }}</p>
          @endif
        </div>

        <div class="sm:text-right">
          <span class="text-[10px] text-[var(--ink-3)] block uppercase tracking-wider">Scheduled Session</span>
          <p class="text-sm font-bold text-[var(--ink)]">{{ $booking->starts_at->timezone($timezone)->format('D, d M Y') }}</p>
          <p class="text-xs font-semibold text-[var(--brand-text)]">
            {{ $booking->starts_at->timezone($timezone)->format('g:i A') }} – {{ $booking->ends_at?->timezone($timezone)->format('g:i A') }} {{ $zoneLabel }}
          </p>
        </div>
      </div>

      {{-- Key details --}}
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
        <div class="space-y-1 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
          <span class="text-[var(--ink-3)] block">Assigned Artist</span>
          <p class="font-bold text-[var(--ink)]">{{ $booking->staffMember?->name ?? 'Anyone available' }}</p>
          <p class="t-meta">{{ $service ? $service->durationLabel() : 'Duration on request' }}</p>
        </div>

        <div class="space-y-1 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
          <span class="text-[var(--ink-3)] block">Studio Location</span>
          <p class="font-bold text-[var(--ink)]">{{ $page->name }}</p>
          <p class="t-meta">{{ $where ?: 'Address not set yet' }}</p>
        </div>

        <div class="space-y-1 p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
          <span class="text-[var(--ink-3)] block">Booked By</span>
          <p class="font-bold text-[var(--ink)]">{{ $booking->customer_name ?: '—' }}</p>
          <p class="t-meta">{{ $booking->customer_phone ?: 'No number on file' }}</p>
        </div>
      </div>

      {{-- Payment breakdown. Every figure is off the booking row, which was priced
           by the server from the service when it was created. --}}
      <div class="p-6 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] space-y-4">
        <div class="flex items-center justify-between gap-4">
          <span class="inline-flex items-center gap-2 text-xs font-bold text-[var(--brand-text)]">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Payment Status</span>
          </span>

          <span class="font-mono text-xs font-bold text-[var(--brand-text)]">Reference {{ $booking->reference }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 text-xs">
          <div>
            <span class="text-[var(--ink-3)] block">Total Package Value</span>
            <p class="t-price-sm">{{ Money::format($totalKobo) }}</p>
          </div>

          <div>
            <span class="text-[var(--ink-3)] block">Paid at Booking</span>
            @if ($depositKobo > 0)
              <p class="t-price-sm text-[var(--ok-text)]">{{ Money::format($depositKobo) }} (Paid)</p>
            @else
              <p class="t-price-sm">{{ $totalKobo > 0 ? 'Nothing paid' : 'Nothing owed' }}</p>
            @endif
          </div>

          <div>
            <span class="text-[var(--ink-3)] block">Balance Payable at the Studio</span>
            <p class="t-price-sm text-[var(--ink)]">{{ Money::format($balanceKobo) }}</p>
          </div>
        </div>

        @if ($depositKobo > 0)
          <p class="text-[11px] text-[var(--ink-2)] border-t border-[var(--brand-edge)] pt-3 leading-relaxed">
            {{ Money::format($depositKobo) }} was paid at booking and went straight to {{ $page->name }}. The balance is settled at the studio.
          </p>
        @endif
      </div>

      {{-- Actions --}}
      <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
        <div class="flex items-center gap-3 flex-wrap">
          @if ($where !== '')
            <a href="https://maps.google.com/?q={{ urlencode($where) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="px-4 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs font-bold hover:bg-[var(--surface)] transition-colors flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-[var(--brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
              <span>Directions in Google Maps</span>
            </a>
          @endif

          @if ($desk)
            <a href="{{ $desk }}"
               target="_blank"
               rel="noopener noreferrer"
               class="px-4 py-2.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold hover:opacity-95 transition-opacity flex items-center gap-1.5 shadow-sm">
              <x-themes.shared.whatsapp-icon />
              <span>Studio WhatsApp Desk</span>
            </a>
          @endif
        </div>

        <button type="button"
                @click="alert('To reschedule or alter your date, please message the studio desk on WhatsApp at least 48 hours prior to your slot.')"
                class="text-xs text-[var(--ink-3)] hover:text-[var(--ink)] underline">
          Need to reschedule or modify?
        </button>
      </div>
    </div>

    {{-- Preparation & etiquette: what to bring and what to expect. --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs mt-8">
      <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-3">
        <h3 class="text-sm font-bold text-[var(--ink)]">Before Your Session</h3>
        <ul class="space-y-2 text-[var(--ink-2)]">
          <li class="flex items-start gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
            <span>Arrive with clean, bare skin, and skip heavy oils or waterproof mascara on the morning.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
            <span>Bring the outfit, beads, headpiece or veil you want styled, so colours can be matched in daylight.</span>
          </li>
        </ul>
      </div>

      <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-3">
        <h3 class="text-sm font-bold text-[var(--ink)]">Arriving At The Studio</h3>
        <ul class="space-y-2 text-[var(--ink-2)]">
          <li class="flex items-start gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
            <span>Quote your reference on arrival so the team knows which session you are.</span>
          </li>
          <li class="flex items-start gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)] mt-1.5" aria-hidden="true"></span>
            <span>Tell the desk on arrival if anything about your booking has changed.</span>
          </li>
        </ul>
      </div>
    </div>
  @endif
</div>
