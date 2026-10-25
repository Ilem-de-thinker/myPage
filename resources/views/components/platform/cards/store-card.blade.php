@props([
    'store',
    'index' => 0,
    'source' => 'directory',
])

@php
    $url = route('directory.store', $store);
    $goUrl = route('directory.go', ['type' => 'store', 'id' => $store->id, 'source' => $source]);
    $waUrl = route('directory.go', ['type' => 'whatsapp', 'id' => $store->id, 'source' => $source]);
    $initials = $store->initials ?? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($store->name, 0, 2));
    $accentColor = $store->accent_color ?? '#1b5e43';
@endphp

<article data-reveal style="--stagger-index: {{ $index % 4 }}" class="platform-card-base group overflow-hidden border border-[var(--platform-line)] bg-[var(--platform-card)] hover:border-[var(--platform-brand)]">
  {{-- Store Cover Top Band --}}
  <div class="relative h-28 w-full overflow-hidden" style="background: linear-gradient(135deg, {{ $accentColor }}22 0%, var(--platform-surface) 100%);">
    <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle, var(--platform-ink) 1px, transparent 1px); background-size: 14px 14px;"></div>
    
    {{-- Verified Stamp --}}
    @if (!empty($store->verified_at))
      <div class="absolute right-4 top-4 flex items-center gap-1 rounded-full border border-[var(--platform-brand)]/20 bg-white/90 px-2.5 py-1 text-[10px] font-bold text-[var(--platform-brand)] backdrop-blur-xs">
        <svg class="size-3 fill-current" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <span>Verified Merchant</span>
      </div>
    @endif
  </div>

  <div class="relative flex flex-1 flex-col px-6 pb-6 pt-0">
    {{-- Overlapping Brand Monogram/Logo --}}
    <div class="-mt-10 mb-4 flex items-end justify-between">
      <div class="flex size-20 items-center justify-center rounded-2xl border-4 border-white bg-[var(--platform-paper)] shadow-md transition-transform group-hover:scale-105" style="color: {{ $accentColor }}">
        @if (!empty($store->logo_path))
          <img src="{{ \Illuminate\Support\Facades\Storage::url($store->logo_path) }}" alt="" class="size-full rounded-xl object-cover">
        @else
          <span class="font-display text-2xl font-extrabold">{{ $initials }}</span>
        @endif
      </div>

      <div class="text-right">
        <span class="block text-xs font-semibold text-[var(--platform-ink-muted)]">
          {{ $store->locationLabel() ?? ($store->city ?? 'Nigeria') }}
        </span>
      </div>
    </div>

    <h3 class="font-display text-2xl font-bold tracking-tight text-[var(--platform-ink)] group-hover:text-[var(--platform-brand)] transition-colors">
      <a href="{{ $url }}">{{ $store->name }}</a>
    </h3>

    <p class="mt-2 text-sm leading-relaxed text-[var(--platform-ink-muted)] line-clamp-3">
      {{ $store->bio ?? $store->tagline ?? 'Independent merchant operating on sovereign store infrastructure.' }}
    </p>

    <div class="mt-auto pt-6 flex items-center justify-between gap-3 border-t border-[var(--platform-line)]">
      <a href="{{ $url }}" class="link-draw text-xs font-bold text-[var(--platform-ink)]">
        View catalog & details
      </a>

      <div class="flex items-center gap-2">
        <a href="{{ $waUrl }}" title="Direct WhatsApp" class="inline-flex size-9 items-center justify-center rounded-xl border border-[var(--platform-line)] text-[var(--platform-brand)] hover:bg-[var(--platform-brand-wash)] transition-colors">
          <x-ui.icon name="whatsapp" class="size-4" />
        </a>
        <a href="{{ $goUrl }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[var(--platform-brand)] px-4 text-xs font-bold text-white hover:bg-[var(--platform-brand-hover)] transition-all">
          <span>Visit store</span>
          <svg class="size-3.5 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
    </div>
  </div>
</article>
