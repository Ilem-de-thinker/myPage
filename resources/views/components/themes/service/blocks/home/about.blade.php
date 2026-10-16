@props(['page', 'block'])

@php
    $heading = $block->field('heading');
    $body = $block->field('body');
    $image = $block->field('image');
@endphp

@if ($body || $image)
  {{-- The text column is the wider one on purpose: this is the seller's own
       words, and a narrow column under a wide photograph would be reading a
       caption. --}}
  <section class="shell section-y">
    <div class="grid items-center gap-8 md:grid-cols-12 md:gap-10">
      <div class="md:col-span-7">
        @if ($heading)
          <h2 class="t-display-2 mb-5">{{ $heading }}</h2>
        @endif

        @if ($body)
          {{-- Sanitised on save as well as here: the controller is the real gate. --}}
          <div class="t-body-lg t-measure-narrow text-[color:var(--ink-2)] [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-[family-name:var(--font-heading-stack)] [&_h2]:text-xl [&_h2]:font-extrabold [&_li]:ml-5 [&_p]:mt-4">
            {!! \App\Shared\Support\Html::sanitize($body) !!}
          </div>
        @endif
      </div>

      @if ($image)
        <div class="md:col-span-5">
          <div class="grain relative overflow-hidden bg-[var(--brand-wash)]">
            <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt=""
                 class="aspect-[4/5] w-full object-cover md:aspect-[3/4]" loading="lazy" decoding="async">
          </div>
        </div>
      @endif
    </div>
  </section>
@endif