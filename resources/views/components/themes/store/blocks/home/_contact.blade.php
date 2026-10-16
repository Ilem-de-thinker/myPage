@php
    use App\Shared\Support\WhatsApp;

    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I would like to book a private consultation.')
        : null;

    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');
@endphp

@if ($whatsapp || $where || $page->public_email)
  {{--
    A row, not a card. The contact block exists to get the conversation started,
    and a centred box with a button floating in it is the shape every generated
    page uses for the same job.
  --}}
  <div class="card p-8 sm:p-12 bg-[var(--surface)] border border-[var(--rule)] space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
      <div class="md:col-span-7 space-y-4">
        <span class="t-meta">Studio Consultations</span>

        <h2 class="t-display-2 text-[var(--ink)]">
          {{ $page->locationLabel() ? 'Visit our '.$page->city.'.' : 'Get in touch with the studio.' }}
        </h2>

        {{-- Only the details the seller actually published. A line reading
             "Not set yet" is worse than no line. --}}
        <div class="space-y-1.5 text-xs text-[var(--ink-2)] pt-2">
          @if ($where)
            <p><strong class="text-[var(--ink)]">Address:</strong> {{ $where }}</p>
          @endif

          @if ($page->whatsapp_number)
            <p><strong class="text-[var(--ink)]">WhatsApp:</strong> {{ $page->whatsapp_number }}</p>
          @endif

          @if ($page->public_email)
            <p><strong class="text-[var(--ink)]">Email:</strong> <a href="mailto:{{ $page->public_email }}" class="underline">{{ $page->public_email }}</a></p>
          @endif
        </div>
      </div>

      <div class="md:col-span-5 flex flex-col gap-3">
        @if ($whatsapp)
          <a href="{{ $whatsapp }}"
             target="_blank"
             rel="noopener noreferrer"
             class="h-12 px-6 rounded-[var(--radius)] bg-[#25D366] text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm hover:opacity-95 transition-opacity">
            <span>Book a Fitting on WhatsApp</span>
          </a>
        @endif

        <a href="{{ route('storefront.contact', ['slug' => $page->slug]) }}" class="h-12 px-6 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] font-bold text-xs flex items-center justify-center hover:bg-[var(--surface)] transition-colors">
          Send Direct Inquiry
        </a>
      </div>
    </div>
  </div>
@endif