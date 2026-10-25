@props([
    'text' => 'FIND IT. GO THERE.',
    'speed' => '0.04',
    'class' => 'top-20 -left-10 text-[clamp(4.5rem,14vw,13rem)]',
])

{{-- Outlined Ghost Typography drifting behind content --}}
<div data-parallax="{{ $speed }}" class="platform-ghost-type {{ $class }}" aria-hidden="true">
  {{ $text }}
</div>
