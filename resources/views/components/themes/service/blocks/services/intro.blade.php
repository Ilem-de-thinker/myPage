@props(['page', 'block'])

@php
    $heading = $block->field('heading');
    $body = $block->field('body');
@endphp

@if ($heading || $body)
  <section class="mx-auto max-w-5xl px-4 pt-14">
    @if ($heading)
      <h1 class="text-3xl font-extrabold tracking-tight" style="font-family: var(--font-heading-stack)">{{ $heading }}</h1>
    @endif

    @if ($body)
      <p class="mt-3 max-w-xl text-sm text-slate-600">{{ $body }}</p>
    @endif
  </section>
@endif
