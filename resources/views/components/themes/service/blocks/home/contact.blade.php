@props(['page', 'block'])

<section class="shell section-y">
  @if ($heading = $block->field('heading'))
    <h2 class="t-display-2 mb-8">{{ $heading }}</h2>
  @endif

  @php
    $whatsapp = \App\Shared\Support\WhatsApp::buyerLink($page->whatsapp_number, 'Hello '.$page->name.', I found your page.');
  @endphp

  @if ($whatsapp)
    <div class="flex flex-col items-start gap-5 rounded-lg bg-[var(--brand-wash)] p-7 md:flex-row md:items-center md:justify-between md:p-9">
      <div>
        <p class="t-title">Message {{ $page->name }} on WhatsApp</p>
        <p class="t-body mt-1 text-[color:var(--ink-2)]">Ask about a service, check a time, or book directly.</p>
      </div>

      <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
         class="flex min-h-12 shrink-0 items-center gap-2.5 rounded-md px-7 text-[15px] font-bold transition-opacity hover:opacity-90"
         style="background: var(--brand); color: var(--brand-ink)">
        <x-themes.shared.whatsapp-icon />
        Open WhatsApp
      </a>
    </div>
  @endif
</section>
