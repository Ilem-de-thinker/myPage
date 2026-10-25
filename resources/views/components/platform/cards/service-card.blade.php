@props([
    'service',
    'index' => 0,
])

@php
    $store = $service->storePage;
    $url = route('directory.go', ['type' => 'service', 'id' => $service->id, 'source' => 'directory']);
    $waUrl = route('directory.go', ['type' => 'whatsapp', 'id' => $store->id ?? 1, 'source' => 'directory']);
    $price = method_exists($service, 'priceLabel') ? $service->priceLabel() : ($service->priceLabel ?? 'Rate on request');
    $reportUrl = $store ? route('directory.store', ['storePage' => $store, 'report' => 'service', 'id' => $service->id]).'#report' : null;
@endphp

<article data-reveal style="--stagger-index: {{ $index % 6 }}" class="platform-card-base group border-l-4 border-l-[var(--platform-accent)]">
  <div class="flex flex-1 flex-col p-6">
    <div class="flex items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-2">
        <span class="rounded-full bg-[var(--platform-accent-wash)] px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-[var(--platform-accent)]">
          Service Booking
        </span>
        @if (!empty($service->turnaround))
          <span class="text-[11px] font-medium text-[var(--platform-ink-muted)]">· {{ $service->turnaround }}</span>
        @endif
      </div>
      <span class="text-xs font-semibold text-[var(--platform-ink-muted)]">{{ $store->city ?? 'Nigeria' }}</span>
    </div>

    <h3 class="mt-3 font-display text-xl font-bold leading-snug tracking-tight text-[var(--platform-ink)] group-hover:text-[var(--platform-accent)] transition-colors">
      <a href="{{ $url }}">{{ $service->name }}</a>
    </h3>

    <div class="mt-1 flex items-center gap-1.5 text-xs text-[var(--platform-ink-muted)]">
      <span class="font-semibold text-[var(--platform-ink)]">{{ $store->name ?? 'Studio' }}</span>
      @if (!empty($store->verified_at))
        <span class="inline-flex items-center text-[var(--platform-brand)]" title="Verified Studio">
          <svg class="size-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </span>
      @endif
    </div>

    <p class="mt-3 text-xs leading-relaxed text-[var(--platform-ink-muted)] line-clamp-3">
      {{ strip_tags((string) ($service->description ?? 'Book time, consultation or studio service directly with this provider.')) }}
    </p>

    @if ($reportUrl)
      <a href="{{ $reportUrl }}" class="mt-2 inline-block text-[10px] font-semibold text-[var(--platform-ink-muted)] hover:text-[var(--platform-ink)] hover:underline">Report this listing</a>
    @endif

    <div class="mt-auto pt-6 flex items-center justify-between gap-3 border-t border-[var(--platform-line)]">
      <div>
        <span class="block text-[10px] font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Rate</span>
        <span class="font-display text-base font-extrabold text-[var(--platform-ink)]">
          {{ $price }}
        </span>
      </div>

      <div class="flex items-center gap-2">
        <a href="{{ $waUrl }}" title="Chat with provider" class="inline-flex size-9 items-center justify-center rounded-xl border border-[var(--platform-line)] text-[var(--platform-accent)] hover:bg-[var(--platform-accent-wash)] transition-colors">
          <x-ui.icon name="whatsapp" class="size-4" />
        </a>
        <a href="{{ $url }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[var(--platform-ink)] px-3 text-xs font-bold text-white hover:bg-[var(--platform-accent)] transition-all">
          <span>Book on website</span>
          <svg class="size-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
    </div>
  </div>
</article>
