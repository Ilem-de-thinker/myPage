@props(['page', 'block'])

@php
    use App\Shared\Support\WhatsApp;

    $headline = $block->field('headline');
    $subtext = $block->field('subtext');
    // The cover photo is a page-wide setting, so it wins over a hero image. A
    // seller who set one should not have to set the other as well.
    $image = $page->heroImage();
    $button = $block->field('button_label') ?: 'Shop the collection';
    $whatsapp = filled($page->whatsapp_number)
        ? WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your storefront.')
        : null;
    $location = $page->locationLabel();
@endphp

{{--
  The photograph is the page, not a card beside it. Text sits on the image over
  a dark scrim, because a seller's phone photo has no way of knowing how light
  the words on it will be, and half of them are taken in bright daylight.

  The scrim is neutral ink rather than a brand tint: it has to darken a
  photograph of any colour without recolouring it, and it has to keep the
  headline readable whether the seller chose yellow or black.

  No cover and no hero image is a legitimate state, not a broken one: the
  headline simply takes the full measure on paper.
--}}
<section class="relative min-h-[85vh] flex items-center justify-center overflow-hidden pt-16">
  @if ($image)
    <div class="absolute inset-0 -z-20">
      <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}"
           alt=""
           class="w-full h-full object-cover"
           fetchpriority="high" decoding="async">

      {{-- Three stops: heaviest under the words, lightest at the top so the nav
           can stay transparent over it. --}}
      <div class="absolute inset-0 bg-gradient-to-t from-[var(--ink)]/85 via-[var(--ink)]/62 to-[var(--ink)]/45"></div>
      <div class="grain" aria-hidden="true"></div>
    </div>
  @endif

  <div @class([
      'shell text-center py-20 relative z-10 max-w-4xl',
      'text-white' => $image,
  ])>
    @if ($location || $page->isPublished())
      <span @class([
          'inline-flex items-center gap-2 px-3 py-1 rounded-[var(--radius-pill)] text-xs font-semibold mb-6',
          'bg-white/10 backdrop-blur-md border border-white/20' => $image,
          'bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)]' => ! $image,
      ])>
        <span @class([
            'w-2 h-2 rounded-full',
            'bg-emerald-400' => $image,
            'bg-[var(--brand)]' => ! $image,
        ]) aria-hidden="true"></span>
        <span>{{ $location ? $location.' · ' : '' }}Verified Sovereign Store</span>
      </span>
    @endif

    @if ($headline)
      <h1 class="t-display-1 mb-6 {{ $image ? 'text-white' : '' }}">{{ $headline }}</h1>
    @endif

    @if ($subtext)
      <p @class([
          't-body-lg mb-8 t-measure mx-auto',
          'text-slate-200' => $image,
          'text-[var(--ink-2)]' => ! $image,
      ])>{{ $subtext }}</p>
    @endif

    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
      <a href="{{ route('storefront.products', ['slug' => $page->slug]) }}"
         class="h-12 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-sm flex items-center justify-center hover:opacity-90 transition-opacity">
        {{ $button }} →
      </a>

      @if ($whatsapp)
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
           class="h-12 px-6 rounded-[var(--radius)] font-semibold text-sm flex items-center justify-center transition-colors {{ $image ? 'bg-white/15 backdrop-blur-md text-white border border-white/25 hover:bg-white/25' : 'bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--brand-wash)]' }}">
          Order on WhatsApp
        </a>
      @endif
    </div>
  </div>
</section>

{{--
  The trust strip. Four standing promises every shop on the platform makes, so
  this is the platform's copy and not the seller's: it renders from the theme,
  with no block behind it, and none of it names a place, a price or a person.
--}}
<section class="border-y border-[var(--rule)] bg-[var(--surface)]">
  <div class="shell">
    <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-[var(--rule)] border-x border-[var(--rule)]">

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">01 / Authenticity</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Direct From The Seller</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">One shop, one conversation, no middlemen.</p>
      </div>

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">02 / Dispatch</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Tracked Delivery</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">A waybill reference on every order that leaves.</p>
      </div>

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">03 / Security</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Escrow Protected</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">Payment held safely until you inspect the goods.</p>
      </div>

      <div class="p-6 text-center">
        <span class="t-meta block mb-1">04 / Concierge</span>
        <h3 class="text-sm font-bold text-[var(--ink)]">Direct WhatsApp</h3>
        <p class="text-xs text-[var(--ink-2)] mt-1">Ask a question and get an answer from a person.</p>
      </div>

    </div>
  </div>
</section>