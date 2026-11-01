@props(['page', 'services' => null, 'staff' => null])

@php
    use App\Domains\Booking\Actions\QuoteServiceFulfilment;
    use Illuminate\Support\Carbon;

    // The widget needs real, bookable services. A service page with none yet
    // shows an honest empty state instead of a form that cannot submit.
    $services = $services ?? collect();
    $staff = $staff ?? collect();

    /*
     * "Book this service" on a service page arrives with ?service=. Without this
     * the widget ignored it and silently selected whichever service happened to
     * be first, which is how a customer ends up booking the wrong appointment.
     */
    $preselected = $services->firstWhere('id', (int) request('service')) ?? $services->first();

    $timezone = $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');

    /*
     * Three months of real calendar, Monday-first, as grids of cells so the month
     * a visitor is looking at is the month they navigated to. Leading cells are
     * null: they hold the position of the first of the month without pretending to
     * be a day anybody can book.
     */
    $months = collect(range(0, 2))->map(function (int $offset) use ($timezone) {
        $start = Carbon::now($timezone)->startOfMonth()->addMonths($offset);

        $cells = array_fill(0, $start->dayOfWeekIso - 1, null);

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->copy()->addDays($day - 1);

            $cells[] = [
                'value' => $date->toDateString(),
                'day' => (string) $day,
                'label' => $date->format('j F Y'),
            ];
        }

        return [
            'label' => $start->format('F Y'),
            'cells' => $cells,
        ];
    })->values();

    $firstDay = collect($months[0]['cells'])->filter()->first();

    // The photograph in the summary column. The seller's own cover, else the first
    // service that has a picture, else nothing at all.
    $snapshot = $page->heroImage() ?: $services->firstWhere('image_path')?->image_path;

    /*
     * A travel fee is only owed when the service happens at the buyer's place.
     * Resolve every service once against the same profile so the card, the
     * summary and CreateBooking all charge the same figure.
     */
    $profile = $page->deliveryProfile()->first();
    $quoteFulfilment = app(QuoteServiceFulfilment::class);
    $fulfilments = $services
        ->mapWithKeys(fn ($service) => [$service->id => $quoteFulfilment->handle($service, $profile)]);

    $preselectedFulfilment = $preselected
        ? $fulfilments[$preselected->id]
        : ['mode' => \App\Domains\Booking\Enums\ServiceMode::AtLocation, 'travel_fee_kobo' => 0];
    $travelFeeKobo = $preselectedFulfilment['travel_fee_kobo'];
    $dueKobo = $preselected ? $preselected->amountDueKobo() + $travelFeeKobo : 0;
    $balanceKobo = $preselected ? max(0, $preselected->price_kobo - $preselected->depositKobo()) : 0;

    // Buyer-facing wording: "at your location" would read as the buyer's place,
    // which is the opposite of what that mode means here.
    $modeLabels = [
        'at_location' => 'At the studio',
        'home' => 'We come to you',
        'remote' => 'Remote / online',
    ];
@endphp

{{-- The same shell as every other storefront section, so the widget
     sits on the page rather than in a narrower box inside it. --}}
<section class="shell pb-16 md:pb-24">
  @if ($services->isEmpty())
    <div class="card rounded-[var(--radius-lg)] border border-[var(--rule)] bg-[var(--paper)] px-6 py-14 text-center">
      <p class="t-title text-[var(--ink)]">Bookings open soon.</p>
      <p class="t-meta mt-1">This page has no services listed yet.</p>
    </div>
  @else
    <div x-data="bookingWidget(@jsExpr([
           'services' => $services->map(fn ($s) => [
               'id' => $s->id,
               'name' => $s->name,
               'duration' => $s->durationLabel(),
               'price' => $s->priceLabel(),
               'due' => \App\Shared\Support\Money::format($s->amountDueKobo() + $fulfilments[$s->id]['travel_fee_kobo']),
               'balance' => \App\Shared\Support\Money::format(max(0, $s->price_kobo - $s->depositKobo())),
               'travelFee' => \App\Shared\Support\Money::format($fulfilments[$s->id]['travel_fee_kobo']),
               'hasTravelFee' => $fulfilments[$s->id]['travel_fee_kobo'] > 0,
               'modeLabel' => $modeLabels[$fulfilments[$s->id]['mode']->value] ?? $fulfilments[$s->id]['mode']->label(),
               'hasDeposit' => $s->hasDeposit(),
           ])->values(),
           'months' => $months,
           'staff' => $staff->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values(),
           'serviceId' => (string) $preselected->id,
           'date' => $firstDay['value'],
           'availabilityUrl' => \App\Shared\Support\StoreUrl::path('storefront.availability', ['slug' => $page->slug]),
           'whatsappEndpoint' => \App\Shared\Support\StoreUrl::path('storefront.booking.whatsapp', ['slug' => $page->slug]),
           'csrf' => csrf_token(),
       ]))"
         x-init="loadSlots()">

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        {{-- Left: package chooser, calendar, client details --}}
        <div class="lg:col-span-8 space-y-8">
          <form method="POST" action="{{ \App\Shared\Support\StoreUrl::path('storefront.booking.store', ['slug' => $page->slug]) }}">
            @csrf

            <input type="hidden" name="service" :value="serviceId">
            <input type="hidden" name="staff" :value="staffId">
            <input type="hidden" name="starts_at" :value="startsAt" required>

            @if ($errors->any())
              <div class="mb-8 rounded-[var(--radius)] border border-[var(--danger-edge)] bg-[var(--danger-wash)] p-4">
                <ul class="space-y-1 text-xs text-[var(--danger-text)]">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            {{-- 1. Choose the service --}}
            <div class="card p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-4">
              <div class="flex items-center justify-between border-b border-[var(--rule)] pb-4">
                <h2 class="text-base font-bold text-[var(--ink)]">1. Choose Ceremonial Package</h2>
                <span class="t-meta">{{ $page->name }}</span>
              </div>

              <div x-data="{ showAll: {{ ($preselected && $services->values()->search(fn ($s) => $s->id === $preselected->id) >= 6) ? 'true' : 'false' }} }">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-72 overflow-y-auto pr-1">
                  @foreach ($services as $index => $service)
                    <button type="button"
                            x-show="showAll || {{ $index }} < 6"
                            @click="selectService('{{ $service->id }}')"
                            :class="serviceId === '{{ $service->id }}' ? 'border-[var(--brand)] bg-[var(--brand-wash)] ring-1 ring-[var(--brand)]' : 'border-[var(--rule)] bg-[var(--paper)] hover:border-[var(--brand-edge)]'"
                            :aria-pressed="serviceId === '{{ $service->id }}'"
                            class="p-4 rounded-[var(--radius)] border text-left transition-all">
                      <div class="flex justify-between items-start gap-3">
                        <span class="text-xs font-bold text-[var(--ink)]">{{ $service->name }}</span>
                        <span class="text-right">
                          <span class="t-price-sm">{{ $service->priceLabel() }}</span>
                          @if ($service->isOnSale())
                            <del class="ml-1 text-[10px] text-[var(--ink-3)] tabular-nums">{{ \App\Shared\Support\Money::format($service->originalPriceKobo()) }}</del>
                          @endif
                        </span>
                      </div>
                      <p class="text-[11px] text-[var(--ink-3)] mt-1">
                        {{ $service->durationLabel() }}
                        @if ($service->hasDeposit())
                          · {{ \App\Shared\Support\Money::format($service->depositKobo()) }} Deposit
                        @endif
                        @if ($fulfilments[$service->id]['travel_fee_kobo'] > 0)
                          · Travel {{ \App\Shared\Support\Money::format($fulfilments[$service->id]['travel_fee_kobo']) }}
                        @endif
                      </p>
                    </button>
                  @endforeach
                </div>
                @if ($services->count() > 6)
                  <button type="button"
                          @click="showAll = !showAll"
                          class="mt-3 text-xs font-semibold text-[var(--brand)] hover:underline">
                    <span x-text="showAll ? 'Show fewer services' : 'Show all {{ $services->count() }} services'"></span>
                  </button>
                @endif
              </div>

              {{-- Only worth asking when there is a choice to make. --}}
              @if ($staff->count() > 1)
                <label class="block pt-2">
                  <span class="text-xs font-bold text-[var(--ink)]">Who would you like?</span>
                  <select x-model="staffId" x-on:change="loadSlots()"
                          class="mt-1.5 w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
                    <option value="">Anyone who is free</option>
                    @foreach ($staff as $member)
                      <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                  </select>
                </label>
              @endif
            </div>

            {{-- 2. Pick a date and a time --}}
            <div class="card p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-6 mt-8">
              <div class="flex items-center justify-between border-b border-[var(--rule)] pb-4">
                <div>
                  <h2 class="text-base font-bold text-[var(--ink)]">2. Select Appointment Date</h2>
                  <p class="t-meta" x-text="months[month].label">{{ $months[0]['label'] }} Studio Schedule</p>
                </div>

                <div class="flex items-center gap-2">
                  <button type="button"
                          @click="previousMonth()"
                          :disabled="month === 0"
                          class="p-2 rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink)] hover:bg-[var(--surface)] disabled:opacity-30"
                          aria-label="Previous month">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                  </button>

                  <button type="button"
                          @click="nextMonth()"
                          :disabled="month === months.length - 1"
                          class="p-2 rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink)] hover:bg-[var(--surface)] disabled:opacity-30"
                          aria-label="Next month">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                  </button>
                </div>
              </div>

              {{-- Days of week header --}}
              <div class="grid grid-cols-7 gap-2 text-center text-xs font-bold text-[var(--ink-3)]">
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
                <span>Sun</span>
              </div>

              {{-- Days grid. The endpoint decides what is bookable; this only draws
                   the month. --}}
              <div class="grid grid-cols-7 gap-2" role="group" :aria-label="months[month].label + ' booking dates'">
                <template x-for="(cell, index) in months[month].cells" :key="index">
                  <div>
                    <template x-if="! cell">
                      <div class="h-12 rounded-[var(--radius)] opacity-20"></div>
                    </template>

                    <template x-if="cell">
                      <button type="button"
                              @click="selectDate(cell)"
                              :class="date === cell.value ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'"
                              class="w-full h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors"
                              :aria-pressed="date === cell.value"
                              :aria-label="cell.label"
                              x-text="cell.day"></button>
                    </template>
                  </div>
                </template>
              </div>

              {{-- Time slots for the day that is chosen --}}
              <div class="pt-4 border-t border-[var(--rule)]">
                <span class="block text-xs font-bold text-[var(--ink)] mb-3">Available Time Slots for Selected Date:</span>

                <p x-show="loading" style="display: none;" class="text-xs text-[var(--ink-3)]">Checking what is free…</p>

                <p x-show="! loading && slots.length === 0" style="display: none;" class="text-xs text-[var(--ink-3)]">
                  Nothing free on this day. Try another date.
                </p>

                <div x-show="! loading && slots.length > 0" style="display: none;" class="flex flex-wrap gap-2.5">
                  <template x-for="slot in slots" :key="slot.starts_at">
                    <button type="button"
                            @click="selectSlot(slot)"
                            :class="startsAt === slot.starts_at ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                            class="px-4 py-2.5 rounded-[var(--radius)] text-xs font-medium tabular-nums transition-colors"
                            :aria-pressed="startsAt === slot.starts_at"
                            x-text="slot.label"></button>
                  </template>
                </div>
              </div>
            </div>

            {{-- 3. Who is coming --}}
            <div class="card p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-5 mt-8">
              <h2 class="text-base font-bold text-[var(--ink)] border-b border-[var(--rule)] pb-4">3. Client Details</h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label for="client-name" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Full Name *</label>
                  <input type="text"
                         id="client-name"
                         name="name"
                         required
                         value="{{ old('name') }}"
                         placeholder="Your full name"
                         class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
                </div>

                <div>
                  <label for="client-phone" class="block text-xs font-bold text-[var(--ink)] mb-1.5">WhatsApp Phone (+234) *</label>
                  <input type="tel"
                         id="client-phone"
                         name="phone"
                         required
                         value="{{ old('phone') }}"
                         placeholder="0803 000 0000"
                         class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
                </div>

                <div class="sm:col-span-2">
                  <label for="client-email" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Email</label>
                  <input type="email"
                         id="client-email"
                         name="email"
                         value="{{ old('email') }}"
                         placeholder="you@example.com"
                         class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
                  <span class="mt-1 block text-[11px] text-[var(--ink-3)]">Where we send your confirmation.</span>
                </div>
              </div>

              <div>
                <label for="client-notes" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Skin Sensitivities, Outfit Colours or Special Notes</label>
                <textarea id="client-notes"
                          name="notes"
                          rows="3"
                          placeholder="Colour you are wearing, allergies, who is coming with you…"
                          class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">{{ old('notes') }}</textarea>
              </div>

              {{--
                The deposit is not a choice here. What a booking owes is the
                service's deposit, or the whole price where the seller set none, and
                that is decided by the server from the row — so the panel beside
                this form states it rather than offering a second option the
                endpoint would ignore.
              --}}

              <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                <p class="t-body text-[var(--ink-2)]">
                  <span class="font-semibold" x-text="dueLabel"></span>
                </p>

                <div class="flex flex-wrap items-center gap-3">
                  <button type="button"
                          :disabled="! startsAt"
                          @click="sendWhatsApp()"
                          class="px-4 py-2.5 rounded-[var(--radius)] text-xs font-semibold text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--surface)] transition-colors disabled:opacity-40">
                    Ask on WhatsApp instead
                  </button>

                  <button type="submit"
                          :disabled="! startsAt"
                          class="px-8 py-3.5 rounded-[var(--radius)] text-xs font-bold text-[var(--brand-ink)] shadow-[var(--shadow-lift)] hover:opacity-95 transition-opacity disabled:opacity-40"
                          style="background: var(--brand)">
                    Book this time
                  </button>
                </div>
              </div>

              <p x-show="whatsappUrl" x-cloak class="text-xs text-[var(--ok-text)]">
                Opening WhatsApp with your message. The seller will confirm your time.
              </p>
            </div>
          </form>
        </div>

        {{-- Right: the running summary --}}
        <div class="lg:col-span-4 sticky top-24 space-y-6">
          <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
            <h2 class="text-sm font-bold text-[var(--ink)] pb-3 border-b border-[var(--rule)]">Booking Summary</h2>

            <div class="space-y-3.5 my-4 text-xs">
              <div class="flex items-start justify-between gap-2">
                <span class="text-[var(--ink-3)]">Service Package</span>
                <span class="font-semibold text-[var(--ink)] text-right" x-text="service ? service.name : ''">{{ $preselected->name }}</span>
              </div>

              <div class="flex items-center justify-between gap-2">
                <span class="text-[var(--ink-3)]">Selected Date</span>
                <span class="font-semibold text-[var(--ink)]" x-text="date">{{ $firstDay['value'] }}</span>
              </div>

              <div class="flex items-center justify-between gap-2">
                <span class="text-[var(--ink-3)]">Time Slot</span>
                <span class="font-semibold text-[var(--ink)]" x-text="slotLabel"></span>
              </div>

              <div class="flex items-center justify-between gap-2">
                <span class="text-[var(--ink-3)]">Duration</span>
                <span class="font-semibold text-[var(--ink)] text-right" x-text="service ? service.duration : ''">{{ $preselected->durationLabel() }}</span>
              </div>

              <div class="flex items-center justify-between gap-2">
                <span class="text-[var(--ink-3)]">Where</span>
                <span class="font-semibold text-[var(--ink)] text-right" x-text="service ? service.modeLabel : ''">{{ $modeLabels[$preselectedFulfilment['mode']->value] ?? $preselectedFulfilment['mode']->label() }}</span>
              </div>

              <div x-show="service && service.hasTravelFee" style="display: none;" class="flex items-center justify-between gap-2">
                <span class="text-[var(--ink-3)]">Travel fee</span>
                <span class="font-semibold text-[var(--ink)] text-right" x-text="service ? service.travelFee : ''">{{ \App\Shared\Support\Money::format($travelFeeKobo) }}</span>
              </div>

              <div class="flex items-center justify-between pt-3 border-t border-[var(--rule)]">
                <span class="font-semibold text-[var(--ink)]" x-text="service && service.hasDeposit ? 'Deposit Due Now' : 'Amount Due Now'">Amount Due Now</span>
                <span class="t-price-sm" x-text="service ? service.due : ''">{{ \App\Shared\Support\Money::format($dueKobo) }}</span>
              </div>

              <div x-show="service && service.hasDeposit" style="display: none;" class="flex items-center justify-between text-[11px]">
                <span class="text-[var(--ink-3)]">Balance at the Studio</span>
                <span class="font-semibold text-[var(--ink-2)]" x-text="service ? service.balance : ''">{{ \App\Shared\Support\Money::format($balanceKobo) }}</span>
              </div>
            </div>

            <div class="p-3.5 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex items-start gap-2.5 text-xs text-[var(--ink-2)]">
              <svg class="w-4 h-4 text-[var(--brand)] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
              <span>Deposit payment: any deposit is paid at booking, straight to the studio.</span>
            </div>

            <p class="t-meta text-center mt-4">
              Protected by the mypage.ng verified booking protocol.
            </p>
          </div>

          {{-- The studio, not a stock photograph of somebody else's. --}}
          @if ($snapshot)
            <div class="card p-4 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-3">
              <div class="plate aspect-[16/9] rounded-[var(--radius)] overflow-hidden">
                <div class="plate-media h-full">
                  <img src="{{ \Illuminate\Support\Facades\Storage::url($snapshot) }}"
                       alt="{{ $page->name }}"
                       class="w-full h-full object-cover" loading="lazy" decoding="async">
                </div>
              </div>

              <div>
                <p class="text-xs font-bold text-[var(--ink)]">{{ $page->name }}</p>
                <p class="t-meta">
                  {{ collect([$page->address, $page->city, $page->state])->filter()->join(', ') ?: 'Address not set yet' }}
                </p>
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
  @endif
</section>
