@props(['page', 'block'])

@php
    $heading = $block->field('heading');
@endphp

<section class="section-y">
  <div class="shell">
    @if ($heading)
      <h2 class="sr-only">{{ $heading }}</h2>
    @endif

    <x-themes.store.blocks.home._contact :page="$page" :block="$block" />
  </div>
</section>