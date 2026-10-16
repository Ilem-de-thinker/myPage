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
  <section class="section-y border-b border-[var(--rule)]">
    <div class="shell">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-12 items-center">
        <div class="md:col-span-6 space-y-6">
          @if ($heading)
            <p class="t-meta">The Story</p>
            <h2 class="t-display-2 text-[var(--ink)]">{{ $heading }}</h2>
          @endif

          @if ($body)
            {{-- Sanitised on save as well as here: the controller is the real gate. --}}
            <div class="t-body text-[var(--ink-2)] t-measure [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:font-[family-name:var(--font-heading-stack)] [&_h2]:text-xl [&_h2]:font-extrabold [&_li]:ml-5 [&_p]:mt-4">
              {!! \App\Shared\Support\Html::sanitize($body) !!}
            </div>
          @endif

          <a href="{{ route('storefront.about', ['slug' => $page->slug]) }}" class="inline-flex items-center gap-2 text-xs font-bold text-[var(--brand-text)] hover:underline">
            <span>Read Our Full Story</span>
            <span aria-hidden="true">→</span>
          </a>
        </div>

        <div class="md:col-span-6">
          <div class="plate aspect-[4/3] rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
            @if ($image)
              <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt=""
                   width="600" height="450" loading="lazy"
                   class="w-full h-full object-cover">
            @endif
            <div class="grain" aria-hidden="true"></div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endif