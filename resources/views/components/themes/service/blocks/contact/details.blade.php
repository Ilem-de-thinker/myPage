@props(['page'])

@php
    use App\Domains\Booking\Models\Service;
    use App\Shared\Support\SocialLinks;
    use App\Shared\Support\WhatsApp;
    use Illuminate\Support\Carbon;

    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');
    $coordinates = filled($page->latitude) && filled($page->longitude);
    $mapQuery = $coordinates ? $page->latitude.','.$page->longitude : $where;
    $email = $page->public_email;
    $social = SocialLinks::forPage($page);
    $whatsapp = $page->whatsapp_number
        ? WhatsApp::link($page, 'Hello '.$page->name.', I have an inquiry regarding appointment slots.')
        : null;

    $timezone = $page->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');
    $zoneLabel = Carbon::now($timezone)->format('T');

    /*
     * Opening hours, from the seller's weekly rows.

     * Grouped by ISO weekday so a seller who opens twice on a Saturday gets both
     * windows printed rather than one of them quietly dropped. A day with no row
     * is closed, and says so: an absent line reads as an oversight, not a closure.
     */
    $businessHours = $page->workingHours->whereNull('staff_member_id')->groupBy('weekday');

    // ISO weekday number to name. Anchored on a Monday rather than built by string
    // concatenation, which turns weekday 7 into a Saturday.
    $weekdayNames = collect(range(1, 7))->mapWithKeys(
        fn (int $weekday) => [$weekday => Carbon::createFromDate(2024, 1, 1)->addDays($weekday - 1)->format('l')]
    );

    // Closures still to come. A holiday from last March is not why somebody is
    // ringing about next Saturday.
    $closures = $page->timeOff
        ->filter(fn ($closure) => $closure->ends_at === null || $closure->ends_at->isFuture())
        ->sortBy('starts_at')
        ->values();

    // Real photographs of the room, not stock ones: the seller's own service
    // images are the only pictures this page can honestly caption.
    $showroom = Service::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->whereNotNull('image_path')
        ->orderBy('position')
        ->take(2)
        ->get();

    $mapsQuery = $mapQuery;
    $latitude = $coordinates ? (float) $page->latitude : null;
    $longitude = $coordinates ? (float) $page->longitude : null;
    $mapEmbedUrl = \App\Shared\Support\MapProvider::embedUrl($latitude, $longitude, $mapsQuery);
    $directionsUrl = \App\Shared\Support\MapProvider::externalUrl($latitude, $longitude, $mapsQuery);
@endphp

<div class="shell pb-12">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

    {{-- Left: studio info cards --}}
    <div class="lg:col-span-5 space-y-6">

      @if ($where || $businessHours->isNotEmpty())
        <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-4">
          <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>

            <div>
              <h2 class="t-title text-[var(--ink)]">{{ $page->name }}</h2>

              @if ($where)
                <p class="text-xs text-[var(--ink-2)] mt-1">{{ $where }}</p>
              @else
                <p class="t-meta mt-1">Address not set yet</p>
              @endif

              <p class="t-meta mt-2">All times are {{ $zoneLabel }}</p>
            </div>
          </div>

          @if ($businessHours->isNotEmpty())
            <div class="pt-4 border-t border-[var(--rule)] space-y-2 text-xs">
              @foreach ($weekdayNames as $weekday => $weekdayName)
                @php
                    $windows = $businessHours->get($weekday, collect())
                        ->map(fn ($hour) => Carbon::parse('2000-01-01 '.$hour->start_time)->format('g:i A')
                            .' – '.Carbon::parse('2000-01-01 '.$hour->end_time)->format('g:i A'))
                        ->join(', ');
                @endphp
                <div class="flex justify-between gap-4 py-1">
                  <span class="text-[var(--ink-3)]">{{ $weekdayName }}</span>

                  @if ($windows !== '')
                    <span class="font-semibold text-[var(--ink)] text-right">{{ $windows }}</span>
                  @else
                    <span class="text-[var(--ink-3)]">Closed</span>
                  @endif
                </div>
              @endforeach
            </div>
          @endif

          {{-- One-off closures: a holiday is the reason somebody rings instead of
               booking, and it is not on the weekly grid. --}}
          @foreach ($closures as $closure)
            <div class="pt-4 border-t border-[var(--rule)] space-y-1 text-xs">
              <div class="flex justify-between gap-4 py-1">
                <span class="text-[var(--ink-3)]">Closed</span>
                <span class="font-semibold text-[var(--brand-text)] text-right">
                  {{ $closure->starts_at->timezone($timezone)->format('D j M') }}
                  @if ($closure->ends_at && ! $closure->starts_at->isSameDay($closure->ends_at))
                    – {{ $closure->ends_at->timezone($timezone)->format('D j M') }}
                  @endif
                </span>
              </div>

              @if ($closure->reason)
                <p class="t-meta">{{ $closure->reason }}</p>
              @endif
            </div>
          @endforeach
        </div>
      @endif

      @if ($mapsQuery !== '')
        <div class="card rounded-[var(--radius-lg)] border border-[var(--rule)] p-6 space-y-4">
          <div>
            <h2 class="t-title text-[var(--ink)]">Find the studio</h2>
            <p class="text-xs text-[var(--ink-2)] mt-1">Use the pinned location for directions and arrival planning.</p>
          </div>

          @if ($mapEmbedUrl)
            <iframe
                src="{{ $mapEmbedUrl }}"
                class="h-56 w-full rounded-[var(--radius)] border border-[var(--rule)]"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="{{ $page->name }} location"></iframe>
          @endif

          @if ($directionsUrl)
            <a href="{{ $directionsUrl }}"
               target="_blank"
               rel="noopener noreferrer"
               class="inline-flex items-center gap-2 text-xs font-bold text-[var(--brand-text)] hover:underline">
              <span>Open Directions in {{ \App\Shared\Support\MapProvider::label() }}</span>
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
          @endif
        </div>
      @endif

      {{-- Direct channels --}}
      @if ($whatsapp || $email || $social !== [])
        <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-4">
          <h2 class="t-title text-[var(--ink)]">Direct Channels</h2>

          @if ($whatsapp)
            <a href="{{ $whatsapp }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center justify-between p-3 rounded-[var(--radius)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] hover:bg-[var(--brand-wash-2)] transition-colors group">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[var(--brand)] text-[var(--brand-ink)] flex items-center justify-center">
                  <x-themes.shared.whatsapp-icon />
                </div>
                <div>
                  <p class="text-xs font-bold text-[var(--ink)]">Official WhatsApp Desk</p>
                  <p class="t-meta text-[var(--brand-text)]">{{ $page->whatsapp_number }}</p>
                </div>
              </div>
              <span class="text-xs font-semibold text-[var(--brand-text)] group-hover:translate-x-1 transition-transform">Chat &rarr;</span>
            </a>
          @endif

          @if ($email)
            <div class="flex items-center justify-between p-3 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[var(--paper)] text-[var(--ink-2)] border border-[var(--rule)] flex items-center justify-center">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <div>
                  <p class="text-xs font-bold text-[var(--ink)]">Concierge Email</p>
                  <a href="mailto:{{ $email }}" class="t-meta break-all hover:underline">{{ $email }}</a>
                </div>
              </div>
            </div>
          @endif

          @if ($social !== [])
            <div class="flex items-center justify-between p-3 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[var(--paper)] text-[var(--ink-2)] border border-[var(--rule)] flex items-center justify-center">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/></svg>
                </div>
                <div>
                  <p class="text-xs font-bold text-[var(--ink)]">Elsewhere</p>
                  <div class="mt-0.5 flex flex-wrap gap-x-3">
                    @foreach ($social as $link)
                      <a href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow" class="t-meta hover:text-[var(--ink)] hover:underline">{{ $link['label'] }}</a>
                    @endforeach
                  </div>
                </div>
              </div>
            </div>
          @endif
        </div>
      @endif
    </div>

    {{-- Right: the inquiry form --}}
    <div class="lg:col-span-7">
      @if ($whatsapp)
        <div class="card p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-sm">
          <div class="mb-6">
            <h2 class="t-title text-[var(--ink)]">Send a Studio Message</h2>
            <p class="t-body text-xs text-[var(--ink-3)] mt-1">
              Have questions about a date outside the live calendar, or a booking away from the studio? Send a quick note.
            </p>
          </div>

          {{-- Success notification --}}
          <div x-show="submitted"
               x-transition
               class="mb-6 flex items-center gap-3 rounded-[var(--radius)] border border-[var(--ok-edge)] bg-[var(--ok-wash)] p-4 text-xs text-[var(--ok-text)]"
               style="display: none;">
            <svg class="h-5 w-5 shrink-0 text-[var(--ok-text)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>Thank you! Your message has been sent to our WhatsApp desk.</span>
          </div>

          <form @submit.prevent="submitInquiry()" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="contact-name" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Full Name *</label>
                <input type="text"
                       id="contact-name"
                       x-model="name"
                       required
                       placeholder="Your full name"
                       class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
              </div>

              <div>
                <label for="contact-phone" class="block text-xs font-bold text-[var(--ink)] mb-1.5">WhatsApp Number *</label>
                <input type="tel"
                       id="contact-phone"
                       x-model="phone"
                       required
                       placeholder="0803 000 0000"
                       class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
              </div>
            </div>

            <div>
              <label for="event-date" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Ceremony or Shoot Date</label>
              <input type="date"
                     id="event-date"
                     x-model="date"
                     class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]">
            </div>

            <div>
              <label for="message-text" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Inquiry Details *</label>
              <textarea id="message-text"
                        rows="4"
                        x-model="msg"
                        required
                        placeholder="Tell us about your ceremony, the size of your party, or the location…"
                        class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:ring-2 focus:ring-[var(--brand)]"></textarea>
            </div>

            <div class="pt-2">
              <button type="submit"
                      class="w-full sm:w-auto px-8 py-3.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs shadow-sm hover:opacity-95 transition-opacity flex items-center justify-center gap-2">
                <x-themes.shared.whatsapp-icon />
                <span>Connect to Studio Desk WhatsApp</span>
              </button>
            </div>
          </form>
        </div>
      @else
        <div class="card p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-sm">
          <h2 class="t-title text-[var(--ink)]">Send a Studio Message</h2>
          <p class="t-body text-xs text-[var(--ink-3)] mt-1">
            {{ $page->name }} has not published a WhatsApp number yet. Book a slot from the services page instead.
          </p>

          <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.services', ['slug' => $page->slug]) }}"
             class="mt-6 inline-flex items-center rounded-[var(--radius)] px-8 py-3.5 bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs hover:opacity-95 transition-opacity">
            See the service menu
          </a>
        </div>
      @endif
    </div>
  </div>

  {{--
    The showroom strip. Two of the seller's own photographs beside what the room is
    for; nothing is printed here when they have not uploaded any, because a stock
    photograph of somebody else's studio is worse than no photograph.
  --}}
  @if ($showroom->count() >= 2)
    <div class="mt-16 rounded-[var(--radius-lg)] border border-[var(--rule)] overflow-hidden shadow-sm">
      <div class="grid grid-cols-1 md:grid-cols-3">
        <div class="plate aspect-[4/3] md:aspect-auto">
          <div class="plate-media h-full">
            <img src="{{ $showroom[0]->imageUrl() }}"
                 loading="lazy"
                 alt="{{ $showroom[0]->name }}"
                 class="w-full h-full object-cover">
          </div>
        </div>

        <div class="plate aspect-[4/3] md:aspect-auto border-t md:border-t-0 md:border-x border-[var(--rule)]">
          <div class="plate-media h-full">
            <img src="{{ $showroom[1]->imageUrl() }}"
                 loading="lazy"
                 alt="{{ $showroom[1]->name }}"
                 class="w-full h-full object-cover">
          </div>
        </div>

        <div class="p-8 bg-[var(--surface)] flex flex-col justify-between space-y-4">
          <div>
            <span class="t-meta text-[var(--brand-text)] font-semibold">The Studio Experience</span>
            <h2 class="t-title text-[var(--ink)] mt-1">A Room Set Up For One Booking</h2>
            <p class="text-xs text-[var(--ink-2)] mt-2 leading-relaxed">
              The suite is yours for the length of your appointment, lit so your look reads the same under daylight, camera flash and reception lighting.
            </p>
          </div>

        </div>
      </div>
    </div>
  @endif

  {{--
      Reviews of the business itself, and the form to leave one. They live on the
      contact page because that is where somebody who has dealt with the business
      already is.
  --}}
  <div class="mt-16">
    <x-ui.review-list
        :reviews="$page->reviews"
        :rating="$page->rating"
        :count="$page->review_count"
        heading="What clients say" />

    <div class="mt-8">
      <x-ui.review-form
          :action="\App\Shared\Support\StoreUrl::path('storefront.review', ['slug' => $page->slug])"
          :reviewable="$page"
          :subject="$page->name" />
    </div>
  </div>
</div>
