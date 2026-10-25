@props([
    'type' => 'product', // 'product', 'service', 'store'
    'silhouette' => 'tote', // 'tote', 'vase', 'bag', 'garment', 'camera', 'coffee', 'salon', 'artisan'
    'label' => null,
    'sublabel' => null,
    'class' => 'aspect-[4/3] w-full',
])

@php
    $gradients = [
        'tote' => 'from-[#ede5d8] to-[#dfd5c4]',
        'vase' => 'from-[#f2e6dc] to-[#e4cebe]',
        'bag' => 'from-[#e6eae5] to-[#ced9cd]',
        'garment' => 'from-[#ece6f0] to-[#d6c9dd]',
        'camera' => 'from-[#e8ecea] to-[#d0dbd5]',
        'coffee' => 'from-[#efebe5] to-[#dcd2c4]',
        'salon' => 'from-[#f4e8eb] to-[#e6cfd4]',
        'artisan' => 'from-[#ebede8] to-[#d3d9ce]',
    ];
    $gradientClass = $gradients[$silhouette] ?? 'from-[#ece8df] to-[#dad3c4]';
@endphp

<div class="platform-silhouette-box {{ $class }} bg-gradient-to-br {{ $gradientClass }} rounded-[inherit] select-none">
  {{-- Tactile grid dots --}}
  <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, var(--platform-ink-muted) 1px, transparent 1px); background-size: 16px 16px;"></div>

  <div class="relative z-10 flex flex-col items-center justify-center p-6 text-center">
    @if ($silhouette === 'tote' || $silhouette === 'bag')
      <svg class="size-16 text-[var(--platform-brand)] opacity-75 drop-shadow-sm transition-transform duration-500 group-hover:scale-110" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 22L20 54H44L50 22H14Z" />
        <path d="M24 22V14C24 10.6863 26.6863 8 30 8H34C37.3137 8 40 10.6863 40 14V22" />
        <line x1="20" y1="28" x2="44" y2="28" stroke-dasharray="3 3" />
      </svg>
    @elseif ($silhouette === 'vase')
      <svg class="size-16 text-[var(--platform-accent)] opacity-80 drop-shadow-sm transition-transform duration-500 group-hover:scale-110" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M26 8H38M28 8V16C28 20 18 26 18 38C18 50 26 56 32 56C38 56 46 50 46 38C46 26 36 20 36 16V8" />
        <line x1="24" y1="36" x2="40" y2="36" stroke-dasharray="2 2" />
      </svg>
    @elseif ($silhouette === 'garment')
      <svg class="size-16 text-[var(--platform-brand)] opacity-75 drop-shadow-sm transition-transform duration-500 group-hover:scale-110" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 18L24 10L32 16L40 10L52 18L46 30L40 26V54H24V26L18 30L12 18Z" />
        <line x1="32" y1="16" x2="32" y2="54" stroke-dasharray="3 3" />
      </svg>
    @elseif ($silhouette === 'camera')
      <svg class="size-16 text-[var(--platform-ink-muted)] opacity-75 drop-shadow-sm transition-transform duration-500 group-hover:scale-110" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="10" y="18" width="44" height="34" rx="6" />
        <circle cx="32" cy="35" r="10" />
        <circle cx="32" cy="35" r="4" fill="currentColor" opacity="0.3" />
        <path d="M22 18L26 12H38L42 18" />
      </svg>
    @elseif ($silhouette === 'coffee')
      <svg class="size-16 text-[#6d4c41] opacity-75 drop-shadow-sm transition-transform duration-500 group-hover:scale-110" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 16H48V42C48 48 42 54 32 54C22 54 16 48 16 42V16Z" />
        <path d="M48 24H54C56 24 58 26 58 29V33C58 36 56 38 54 38H48" />
        <path d="M24 10Q28 6 24 2M32 10Q36 6 32 2M40 10Q44 6 40 2" stroke-dasharray="2 2" />
      </svg>
    @else
      <svg class="size-16 text-[var(--platform-accent)] opacity-75 drop-shadow-sm transition-transform duration-500 group-hover:scale-110" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="32 8 54 22 54 48 32 58 10 48 10 22" />
        <circle cx="32" cy="36" r="8" />
      </svg>
    @endif

    @if ($label)
      <span class="mt-3 block font-display text-xs font-bold tracking-tight text-[var(--platform-ink)] opacity-90">{{ $label }}</span>
    @endif
    @if ($sublabel)
      <span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-wider text-[var(--platform-ink-muted)]">{{ $sublabel }}</span>
    @endif
  </div>

  {{-- Editorial Authentic Badge in corner --}}
  <div class="absolute bottom-2.5 right-2.5 flex items-center gap-1 rounded-full border border-[var(--platform-line)] bg-white/70 px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest text-[var(--platform-ink-muted)] backdrop-blur-xs">
    <span>Direct</span>
  </div>
</div>
