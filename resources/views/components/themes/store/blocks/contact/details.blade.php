@props(['page', 'block' => null])

@php
    use App\Shared\Support\SocialLinks;
    use App\Shared\Support\StoreUrl;
    use App\Shared\Support\WhatsApp;

    /*
      The contact page's own copy wins, then the seller's business details. An
      address set on the Details tab shows here without being written twice, and
      the opening hours are the one thing still written on the page, because a
      studio's hours are its own words rather than a page-wide setting.
    */
    $hours = $block?->field('hours');
    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');
    $social = SocialLinks::forPage($page);
    $categories = $page->categories()->whereHas('products', fn ($query) => $query->active())->get();

    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I have an enquiry.')
        : null;

    // The bare wa.me endpoint, so the request form can compose its own message
    // rather than shipping a second, fixed one.
    $waEndpoint = filled($page->whatsapp_number)
        ? Str::before(WhatsApp::link($page, 'x'), '=').'='
        : null;

    $mapsUrl = $where
        ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($where)
        : null;

    $hasAny = (bool) ($where || $page->public_email || $page->whatsapp_number || $social || $whatsapp);
@endphp

@if ($hasAny)
  <section class="shell pb-16">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

      {{-- Where the shop is and how to reach it. Every row here is a field the
           seller actually filled in; a row with nothing behind it is left out
           rather than printed with a dash. --}}
      <div class="lg:col-span-5 space-y-6">

        @if ($where)
          <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-4">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
              </div>
              <div>
                <h3 class="t-title text-[var(--ink)]">{{ $page->locationLabel() ?: 'Our Location' }}</h3>
                <p class="text-xs text-[var(--ink-2)] mt-1">
                  @if ($page->address)
                    {{ $page->address }}<br>
                  @endif
                  {{ $page->locationLabel() }}
                </p>
              </div>
            </div>

            @if ($hours)
              <div class="pt-4 border-t border-[var(--rule)] text-xs text-[var(--ink-2)] whitespace-pre-line">{{ $hours }}</div>
            @endif

            @if ($mapsUrl)
              <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                 class="inline-flex items-center gap-2 text-xs font-bold text-[var(--brand-text)] hover:underline">
                <span>Open in Google Maps</span>
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              </a>
            @endif
          </div>
        @endif

        <div class="card p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] space-y-4">
          <h3 class="t-title text-[var(--ink)]">Direct Channels</h3>

          @if ($whatsapp)
            <a href="{{ $whatsapp }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center justify-between p-3 rounded-[var(--radius)] bg-[#25D366]/10 border border-[#25D366]/20 hover:bg-[#25D366]/15 transition-colors group">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center">
                  <x-ui.whatsapp-mark />
                </div>
                <div>
                  <p class="text-xs font-bold text-[var(--ink)]">Official WhatsApp Desk</p>
                  <p class="t-meta text-[#15803d]">{{ $page->whatsapp_number }}</p>
                </div>
              </div>
              <span class="text-xs font-semibold text-[#15803d] group-hover:translate-x-1 transition-transform">Chat &rarr;</span>
            </a>
          @endif

          @if ($page->public_email)
            <div class="flex items-center justify-between gap-3 p-3 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[var(--paper)] text-[var(--ink-2)] border border-[var(--rule)] flex items-center justify-center">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-[var(--ink)]">Concierge Email</p>
                  <a href="mailto:{{ $page->public_email }}" class="t-meta hover:underline break-all">{{ $page->public_email }}</a>
                </div>
              </div>
              <span class="t-meta shrink-0">Formal Orders</span>
            </div>
          @endif

          @if ($social !== [])
            <div class="flex items-center justify-between gap-3 p-3 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)]">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[var(--paper)] text-[var(--ink-2)] border border-[var(--rule)] flex items-center justify-center">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-[var(--ink)]">Elsewhere</p>
                  <p class="t-meta truncate">
                    @foreach ($social as $link)
                      <a href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow" class="hover:underline">{{ $link['label'] }}</a>@if (! $loop->last)<span aria-hidden="true"> · </span>@endif
                    @endforeach
                  </p>
                </div>
              </div>
              <span class="t-meta shrink-0">Follow Along</span>
            </div>
          @endif
        </div>

        <div class="p-5 rounded-[var(--radius-lg)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] flex items-center gap-4">
          <svg class="w-8 h-8 text-[var(--brand-text)] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <div class="text-xs">
            <p class="font-bold text-[var(--ink)]">mypage.ng Escrow Protected</p>
            <p class="text-[var(--ink-2)] mt-0.5">Deposits and retainers are held under safe escrow until the work is confirmed.</p>
          </div>
        </div>
      </div>

      {{-- The request form. There is no inbox to post to — the shop's single
           channel is WhatsApp — so the form composes the message the buyer
           wishes they had written and hands it to that channel. --}}
      <div class="lg:col-span-7">
        <div class="card p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-sm"
             x-data="{
                 formSubmitted: false,
                 name: '',
                 phone: '',
                 date: '',
                 service: @js($categories->first()?->name ?? ''),
                 notes: '',
                 endpoint: @js($waEndpoint),
                 shop: @js($page->name),
                 handleSubmit() {
                     if (!this.endpoint) return;
                     const body = [
                         `Hello ${this.shop}, I would like to make an enquiry.`,
                         this.name ? `Name: ${this.name}` : '',
                         this.phone ? `WhatsApp: ${this.phone}` : '',
                         this.date ? `Date needed: ${this.date}` : '',
                         this.service ? `Interested in: ${this.service}` : '',
                         this.notes ? `Notes: ${this.notes}` : '',
                     ].filter(Boolean).join('\n');

                     window.open(this.endpoint + encodeURIComponent(body), '_blank', 'noopener');
                     this.formSubmitted = true;
                 },
             }">
          <div class="mb-6">
            <h2 class="t-title text-[var(--ink)]">Request a Fitting or Custom Commission</h2>
            <p class="t-body text-xs text-[var(--ink-3)] mt-1">
              Fill in your details below. Your request is formatted and opened in WhatsApp, ready to send to {{ $page->name }}.
            </p>
          </div>

          <div x-show="formSubmitted"
               x-transition
               class="mb-6 p-4 rounded-[var(--radius)] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center gap-3"
               style="display: none;">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>Your request has been opened in WhatsApp. Send it and {{ $page->name }} will reply there.</span>
          </div>

          <form @submit.prevent="handleSubmit()" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="contact-name" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Full Name *</label>
                <input type="text"
                       id="contact-name"
                       x-model="name"
                       required
                       autocomplete="name"
                       placeholder="Your name"
                       class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]">
              </div>

              <div>
                <label for="contact-phone" class="block text-xs font-bold text-[var(--ink)] mb-1.5">WhatsApp Number *</label>
                <input type="tel"
                       id="contact-phone"
                       x-model="phone"
                       required
                       placeholder="0803 000 0000"
                       class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="wedding-date" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Date Needed</label>
                <input type="date"
                       id="wedding-date"
                       x-model="date"
                       class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]">
              </div>

              <div>
                <label for="service-category" class="block text-xs font-bold text-[var(--ink)] mb-1.5">What are you looking for?</label>
                <select id="service-category"
                        x-model="service"
                        class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]">
                  <option value="">Not sure yet</option>
                  @foreach ($categories as $category)
                    <option value="{{ $category->name }}">{{ $category->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div>
              <label for="contact-notes" class="block text-xs font-bold text-[var(--ink)] mb-1.5">Sizing, Colours, or Special Requirements</label>
              <textarea id="contact-notes"
                        rows="4"
                        x-model="notes"
                        placeholder="Anything that would help us quote accurately."
                        class="w-full px-3.5 py-2.5 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-xs focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]"></textarea>
            </div>

            <div class="pt-2">
              <button type="submit" :disabled="! endpoint"
                      class="w-full sm:w-auto px-8 py-3.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs shadow-[var(--shadow-lift)] hover:opacity-95 transition-opacity flex items-center justify-center gap-2 disabled:opacity-50">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.98-.276-.101-.477-.15-.678.15-.2.302-.779.98-.955 1.18-.176.202-.352.226-.653.076-.301-.15-1.272-.469-2.424-1.496-.896-.799-1.501-1.787-1.677-2.088-.176-.302-.019-.465.132-.615.136-.135.301-.352.452-.528.15-.175.2-.299.301-.502.101-.2.05-.376-.025-.526-.075-.15-.678-1.634-.93-2.238-.243-.589-.49-.509-.678-.519-.176-.01-.377-.01-.578-.01s-.527.075-.803.376c-.276.301-1.054 1.03-1.054 2.512s1.08 2.914 1.23 3.115c.15.201 2.126 3.245 5.15 4.553.72.311 1.282.497 1.72.636.723.23 1.381.197 1.901.12.579-.087 1.78-.728 2.032-1.431.251-.703.251-1.306.176-1.431-.076-.126-.277-.202-.578-.352z"/></svg>
                <span>Connect &amp; Send to WhatsApp</span>
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </section>
@endif

{{--
  Reviews of the business itself, and the form to leave one.

  Mounted here rather than on the home page on purpose: this is where somebody
  who has already dealt with the business actually is, and a review form on a
  page nobody reaches after buying is a review form nobody uses.

  $page->reviews arrives approved-only from the controller.
--}}
<section class="shell max-w-3xl pb-16">
  <x-ui.review-list
      :reviews="$page->reviews"
      :rating="$page->rating"
      :count="$page->review_count"
      heading="What customers say" />

  <div class="mt-8">
    <x-ui.review-form
        :action="route('storefront.review', ['slug' => $page->slug])"
        :reviewable="$page"
        :subject="$page->name" />
  </div>
</section>