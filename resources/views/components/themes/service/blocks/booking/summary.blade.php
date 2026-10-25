@props(['page', 'block'])

@php
    /*
     * The introduction above the booking form: the seller's own words when they
     * wrote any, and the design's default when they did not. The form itself is
     * rendered from real, bookable services, so it is not a block a seller can
     * fill in or leave empty.
     */
    $heading = $block->field('heading');
    $body = $block->field('body');
@endphp

<div class="shell py-12">

  {{-- Breadcrumbs --}}
  <nav class="flex items-center gap-2 text-xs text-[var(--ink-3)] mb-6" aria-label="Breadcrumb">
    <a href="{{ \App\Shared\Support\StoreUrl::homePath($page) }}" class="hover:text-[var(--ink)] transition-colors">Home</a>
    <span aria-hidden="true">/</span>
    <span class="text-[var(--ink)] font-semibold" aria-current="page">Book Appointment</span>
  </nav>

  {{-- Page header --}}
  <div class="max-w-2xl mb-10">
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-pill)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold mb-4">
      <span class="w-2 h-2 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
      Live Appointment Dispatch
    </span>

    <h1 class="t-display-1 text-[var(--ink)]">{{ $heading ?: 'Reserve Your Date & Studio Time.' }}</h1>

    @if ($body)
      <p class="t-body-lg text-[var(--ink-2)] mt-4">{{ $body }}</p>
    @else
      <p class="t-body-lg text-[var(--ink-2)] mt-4">
        Pick a service, choose an available calendar date, and secure your session with a deposit paid at booking.
      </p>
    @endif
  </div>
</div>