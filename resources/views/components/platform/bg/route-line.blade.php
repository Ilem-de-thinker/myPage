@props([
    'variant' => 'curve-1',
    'class' => '',
])

<div class="platform-route-svg {{ $class }}" aria-hidden="true">
  @if ($variant === 'curve-1')
    <svg width="420" height="260" viewBox="0 0 420 260" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M 20 20 C 140 10, 260 140, 400 240" class="platform-route-path" />
      <circle cx="20" cy="20" r="4" class="platform-route-pulse" />
      <circle cx="400" cy="240" r="5" fill="var(--platform-brand)" />
    </svg>
  @else
    <svg width="320" height="480" viewBox="0 0 320 480" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M 160 10 C 280 120, 40 280, 160 470" class="platform-route-path" />
      <circle cx="160" cy="10" r="4" class="platform-route-pulse" />
      <circle cx="160" cy="470" r="5" fill="var(--platform-brand)" />
    </svg>
  @endif
</div>
