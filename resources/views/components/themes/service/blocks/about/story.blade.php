@props(['page', 'block'])

@php
    use App\Domains\Booking\Models\StaffMember;

    $heading = $block->field('heading');
    $body = $block->field('body');
    $image = $block->field('image');
    $where = $page->locationLabel();

    /*
     * The name plate under the photograph.

     * The design captioned it with a founder's name and job title. A page has no
     * founder row, so it is the person a customer actually sits with: the first
     * active member of staff, and failing that the business itself. Never a
     * placeholder, because a made-up name under a real photograph is the worst of
     * both.
     */
    $founder = StaffMember::withoutGlobalScopes()
        ->where('store_page_id', $page->id)
        ->where('is_active', true)
        ->orderBy('name')
        ->first();
@endphp

<section class="section-y relative overflow-hidden border-b border-[var(--rule)]">
  <div class="shell">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
      <div class="lg:col-span-7 space-y-6">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold">
          <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
          {{ $where ?: $page->name }}
        </span>

        @if ($heading)
          <h1 class="t-display-1 text-[var(--ink)]">{{ $heading }}</h1>
        @endif

        {{-- Sanitised on save as well as here: the controller is the real gate. --}}
        @if ($body)
          <div class="t-body-lg text-[var(--ink-2)] t-measure [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-[family-name:var(--font-heading-stack)] [&_h2]:text-xl [&_h2]:font-extrabold [&_li]:ml-5 [&_p]:mt-4">
            {!! \App\Shared\Support\Html::sanitize($body) !!}
          </div>
        @endif

        <div class="flex flex-wrap items-center gap-4 pt-2">
          <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', ['slug' => $page->slug]) }}"
             class="inline-flex items-center gap-2 px-6 py-3 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-sm font-bold shadow-[var(--shadow-lift)] hover:opacity-95 transition-opacity">
            <span>Reserve Appointment</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>

          <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.services', ['slug' => $page->slug]) }}"
             class="inline-flex items-center gap-2 px-6 py-3 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] text-sm font-bold hover:bg-[var(--surface)] transition-colors">
            <span>Explore Rate Card</span>
          </a>
        </div>
      </div>

      <div class="lg:col-span-5">
        <div class="plate aspect-[4/5] rounded-[var(--radius-lg)] overflow-hidden shadow-[var(--shadow-lift)] border border-[var(--rule)] relative">
          @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
                 alt=""
                 class="w-full h-full object-cover" loading="lazy" decoding="async">
          @elseif ($founder?->photo_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($founder->photo_path) }}"
                 alt="{{ $founder->name }}"
                 class="w-full h-full object-cover" loading="lazy" decoding="async">
          @endif

          <div class="grain" aria-hidden="true"></div>

          <div class="absolute bottom-4 left-4 right-4 p-4 rounded-[var(--radius)] bg-[var(--card)]/90 backdrop-blur-md border border-[var(--rule)] shadow-sm">
            @if ($founder)
              <p class="text-xs font-bold text-[var(--ink)]">{{ $founder->name }}</p>
              @if ($founder->bio)
                <p class="text-[11px] text-[var(--ink-3)]">{{ \Illuminate\Support\Str::limit($founder->bio, 110) }}</p>
              @endif
            @else
              <p class="text-xs font-bold text-[var(--ink)]">{{ $page->name }}</p>
              @if ($page->tagline)
                <p class="text-[11px] text-[var(--ink-3)]">{{ $page->tagline }}</p>
              @endif
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- The three protocols: what the studio promises, not what it has done. --}}
<section class="section-y border-b border-[var(--rule)]">
  <div class="shell">
    <div class="max-w-2xl mb-12">
      <span class="text-xs font-bold text-[var(--brand-text)] uppercase tracking-wider block mb-2">Our Standards</span>
      <h2 class="t-display-2 text-[var(--ink)]">The Three Sanctuary Protocols</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div class="card p-6 rounded-[var(--radius-lg)] space-y-4">
        <div class="w-10 h-10 rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] flex items-center justify-center font-bold">01</div>
        <h3 class="t-title text-[var(--ink)]">Single-Booking Reservation</h3>
        <p class="text-xs text-[var(--ink-2)] leading-relaxed">
          We never run an assembly-line salon. When your slot begins, the artist and the suite are dedicated solely to you and whoever came with you.
        </p>
      </div>

      <div class="card p-6 rounded-[var(--radius-lg)] space-y-4">
        <div class="w-10 h-10 rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] flex items-center justify-center font-bold">02</div>
        <h3 class="t-title text-[var(--ink)]">Zero-Flashback Formulations</h3>
        <p class="text-xs text-[var(--ink-2)] leading-relaxed">
          Every powder, tint and highlighter is chosen to hold under high-intensity lighting and 4K lenses, so nothing flashbacks to white in a photograph.
        </p>
      </div>

      <div class="card p-6 rounded-[var(--radius-lg)] space-y-4">
        <div class="w-10 h-10 rounded-[var(--radius)] bg-[var(--brand-wash)] text-[var(--brand-text)] flex items-center justify-center font-bold">03</div>
        <h3 class="t-title text-[var(--ink)]">Secure Payment</h3>
        <p class="text-xs text-[var(--ink-2)] leading-relaxed">
          Your deposit is paid at booking through the studio's own payment account. mypage.ng never holds your money.
        </p>
      </div>
    </div>
  </div>
</section>