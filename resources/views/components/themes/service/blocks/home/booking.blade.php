@props(['page', 'block'])

@php
    use App\Domains\Booking\Models\Service;
    use Illuminate\Support\Carbon;

    $services = Service::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->orderBy('position')
        ->get();

    $timezone = $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');

    /*
     * Three real months of calendar, Monday-first, built as a grid of cells so
     * the month the visitor is looking at is the month they clicked. The leading
     * cells are null: they hold the position of the first of the month without
     * pretending to be a day nobody can book.
     */
    $months = collect(range(0, 2))->map(function (int $offset) use ($timezone) {
        $start = Carbon::now($timezone)->startOfMonth()->addMonths($offset);

        $cells = array_fill(0, $start->dayOfWeekIso - 1, null);

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $date = $start->copy()->addDays($day - 1);

            $cells[] = [
                'value' => $date->toDateString(),
                'day' => (string) $day,
                'label' => $date->format('D j M'),
            ];
        }

        return [
            'label' => $start->format('F Y'),
            'cells' => $cells,
        ];
    })->values();

    $firstDay = collect($months[0]['cells'])->filter()->first();
@endphp

{{--
    A way in, not the form itself. A landing page that opens with a booking panel
    tells a visitor who just arrived what to do instead of showing them the
    business; the appointment form lives on the service page, and the standalone
    booking page stays for someone who has not chosen yet.

    Hidden when there is nothing bookable, rather than pointing at a page that can
    only show an empty state.
--}}
@if ($services->isNotEmpty())
  <section class="section-y bg-[var(--surface)] border-b border-[var(--rule)]"
           x-data="{
               serviceId: @js((string) $services->first()->id),
               months: @js($months),
               month: 0,
               date: @js($firstDay['value']),
               slots: [],
               loading: false,
               loadedFor: '',

               /**
                * The same endpoint the booking form uses, so the preview cannot
                * show a day the form would then refuse.
                */
               async loadSlots() {
                 if (!this.serviceId || !this.date) { this.slots = []; return; }

                 const key = this.serviceId + '|' + this.date;

                 if (this.loadedFor === key) { return; }

                 this.loading = true;

                 try {
                   const params = new URLSearchParams({ service: this.serviceId, date: this.date });
                   const response = await fetch(@js(route('storefront.availability', ['slug' => $page->slug])) + '?' + params, {
                     headers: { 'Accept': 'application/json' },
                   });
                   const data = await response.json();
                   this.slots = data.slots || [];
                   this.loadedFor = key;
                 } catch (error) {
                   this.slots = [];
                 } finally {
                   this.loading = false;
                 }
               },
           }"
           x-init="loadSlots()">

    <div class="shell">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        <div class="lg:col-span-5 space-y-4">
          <span class="t-meta text-[var(--brand-text)] font-semibold">Zero Wait Times</span>
          <h2 class="t-display-2 text-[var(--ink)]">Real-Time Studio Availability</h2>
          <p class="t-body text-xs text-[var(--ink-2)] leading-relaxed">
            We never double-book appointments. Your chosen slot reserves the exclusive attention of the artist and the suite for that time.
          </p>

          <div class="p-4 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] space-y-2 text-xs">
            <div class="flex items-center gap-2 text-[var(--brand-text)] font-bold">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              <span>Sovereign Escrow Deposit</span>
            </div>
            <p class="text-[var(--ink-3)]">
              Where a service carries a deposit, it is held in secure escrow to hold your date. The balance is settled at the studio.
            </p>
          </div>

          <a href="{{ route('storefront.book', ['slug' => $page->slug]) }}"
             class="inline-flex items-center gap-2 text-xs font-bold text-[var(--brand-text)] hover:underline pt-2">
            <span>See available times</span>
            <span aria-hidden="true">&rarr;</span>
          </a>
        </div>

        <div class="lg:col-span-7 card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] bg-[var(--paper)]">
          <div class="flex items-center justify-between pb-3 mb-4 border-b border-[var(--rule)]">
            <div>
              <h3 class="text-sm font-bold text-[var(--ink)]" x-text="months[month].label">{{ $months[0]['label'] }}</h3>
              <p class="t-meta">{{ $page->name }} Appointments</p>
            </div>

            <span class="px-2.5 py-0.5 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] text-[var(--brand-text)] text-[10px] font-bold"
                  x-text="loading ? 'Checking…' : slots.length + ' free'">
              Checking…
            </span>
          </div>

          <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-[var(--ink-3)] mb-2">
            <span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
          </div>

          <div class="grid grid-cols-7 gap-1 text-xs" role="group" :aria-label="months[month].label + ' availability'">
            <template x-for="(cell, index) in months[month].cells" :key="index">
              <div>
                <template x-if="! cell">
                  <div class="h-9 rounded opacity-20"></div>
                </template>

                <template x-if="cell">
                  <button type="button"
                          @click="date = cell.value; loadedFor = ''; loadSlots()"
                          :class="date === cell.value
                              ? 'h-9 rounded bg-[var(--brand)] text-[var(--brand-ink)] font-bold ring-1 ring-[var(--brand)]'
                              : 'h-9 rounded border border-[var(--rule)] hover:border-[var(--brand-edge)] text-[var(--ink)]'"
                          class="w-full flex items-center justify-center transition-colors"
                          :aria-pressed="date === cell.value"
                          :aria-label="cell.label"
                          x-text="cell.day"></button>
                </template>
              </div>
            </template>
          </div>

          <div class="mt-4 pt-3 border-t border-[var(--rule)] flex flex-wrap items-center justify-between gap-2 text-[11px] text-[var(--ink-3)]">
            <span>Checked live against {{ $services->first()->name }}</span>

            <span class="flex items-center gap-2">
              <button type="button" @click="month = Math.max(0, month - 1)"
                      :disabled="month === 0"
                      class="p-1 rounded-[var(--radius-sm)] border border-[var(--rule)] disabled:opacity-30"
                      aria-label="Previous month">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
              </button>

              <button type="button" @click="month = Math.min(months.length - 1, month + 1)"
                      :disabled="month === months.length - 1"
                      class="p-1 rounded-[var(--radius-sm)] border border-[var(--rule)] disabled:opacity-30"
                      aria-label="Next month">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
              </button>
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
@endif