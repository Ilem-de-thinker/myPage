@props(['page', 'block' => null])

@php
    use App\Shared\Support\Money;

    $profile = $page->deliveryProfile()->with('zones')->first();
    $zones = $profile?->zones ?? collect();

    $areaNames = $zones->pluck('name')->filter()->values();
    $areaSummary = $areaNames->take(3)->implode(', ');
    $extraAreas = max(0, $areaNames->count() - 3);
@endphp

@if ($profile && ($profile->offers_delivery || $profile->offers_pickup))
  {{--
    A fact strip rather than a block of copy: every line is read from the
    seller's delivery profile, so it cannot promise a fee or an area they have
    not set. The component renders nothing at all until they have.
  --}}
  <section class="border-y border-[var(--brand-edge)] bg-[var(--brand-wash)]" aria-label="Delivery and pickup">
    <div class="shell py-4">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if ($profile->offers_delivery)
          <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[var(--brand-edge)] bg-[var(--surface)] text-[var(--brand-text)]">
              <x-ui.icon name="truck" size="size-4" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-[var(--ink)]">Delivered to your door</p>
              <p class="t-meta mt-0.5">
                @if ($profile->free_over_kobo)
                  Free over {{ Money::format($profile->free_over_kobo) }}
                @elseif ($zones->isNotEmpty())
                  @if ($zones->min('fee_kobo') === 0)
                    Free in some areas
                  @else
                    From {{ Money::format($zones->min('fee_kobo')) }} per delivery
                  @endif
                @else
                  Fee confirmed at checkout
                @endif
              </p>
            </div>
          </div>
        @endif

        @if ($zones->isNotEmpty())
          <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[var(--brand-edge)] bg-[var(--surface)] text-[var(--brand-text)]">
              <x-ui.icon name="map-pin" size="size-4" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-[var(--ink)]">
                {{ $areaNames->count() }} {{ \Illuminate\Support\Str::plural('delivery area', $areaNames->count()) }}
              </p>
              <p class="t-meta mt-0.5">
                {{ $areaSummary }}@if ($extraAreas > 0) and {{ $extraAreas }} more @endif
              </p>
            </div>
          </div>
        @endif

        @if ($profile->offers_pickup)
          <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-[var(--brand-edge)] bg-[var(--surface)] text-[var(--brand-text)]">
              <x-ui.icon name="storefront" size="size-4" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-bold text-[var(--ink)]">Pickup available</p>
              <p class="t-meta mt-0.5">Collect from us at no delivery fee</p>
            </div>
          </div>
        @endif

        @if (filled($profile->notes))
          <div class="flex items-start gap-3 sm:col-span-2 lg:col-span-3">
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
