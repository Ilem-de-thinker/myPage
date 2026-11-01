@props(['page', 'block' => null])

@php
    $profile = $page->deliveryProfile()->with('zones')->first();
    $zones = $profile?->zones ?? collect();

    $areaNames = $zones->pluck('name')->filter()->values();
    $areaSummary = $areaNames->take(3)->implode(', ');
    $extraAreas = max(0, $areaNames->count() - 3);
@endphp

@if ($profile && $profile->offers_home_service)
  {{--
    Services travel differently from products: the fact worth showing is
    whether the provider comes to the customer and how far. Travel fees vary
    per service, so the band never quotes one.
  --}}
  <section class="border-y border-[var(--brand-edge)] bg-[var(--brand-wash)]" aria-label="Service area">
    <div class="shell py-4">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="flex items-start gap-3">
          <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[var(--brand-edge)] bg-[var(--surface)] text-[var(--brand-text)]">
            <x-ui.icon name="home" size="size-4" />
          </span>
          <div class="min-w-0">
            <p class="text-xs font-bold text-[var(--ink)]">We come to you</p>
            <p class="t-meta mt-0.5">
              @if ($profile->radius_km)
                Home service within {{ $profile->radius_km }} km
              @else
                Home service available
              @endif
            </p>
          </div>
        </div>

        @if ($zones->isNotEmpty())
          <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[var(--brand-edge)] bg-[var(--surface)] text-[var(--brand-text)]">
              <x-ui.icon name="map-pin" size="size-4" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-[var(--ink)]">
                {{ $areaNames->count() }} {{ \Illuminate\Support\Str::plural('area', $areaNames->count()) }} covered
              </p>
              <p class="t-meta mt-0.5">
                {{ $areaSummary }}@if ($extraAreas > 0) and {{ $extraAreas }} more @endif
              </p>
            </div>
          </div>
        @endif

        @if (filled($profile->notes))
          <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[var(--brand-edge)] bg-[var(--surface)] text-[var(--brand-text)]">
              <x-ui.icon name="clock" size="size-4" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-[var(--ink)]">Good to know</p>
              <p class="t-meta mt-0.5">{{ $profile->notes }}</p>
            </div>
          </div>
        @endif
      </div>
    </div>
  </section>
@endif
