@php
    /**
     * A service, as a card a buyer can compare against the others on the page.
     *
     * Ported from new_ui/service — the card is the one place a treatment name, a
     * price, a deposit and a duration are read side by side, so every one of them
     * comes off the row rather than off a demo.
     *
     * @var \App\Domains\Booking\Models\Service $service
     * @var \App\Domains\Seller\Models\StorePage $page
     * @var string|null $cardCta the button label, so a listing can say "Book Date"
     */
    $deliverables = $service->deliverableList();
    $where = $page->locationLabel();
    $bookLabel = $cardCta ?? 'Book Slot';
@endphp

<article class="card p-4 group relative">

  {{-- The photograph is the seller's own. No image is not a hole: the plate keeps
       its surface wash, so a card without a picture still reads as a card. --}}
  <div class="plate aspect-[4/3] mb-4">
    <div class="plate-media h-full">
      @if ($service->image_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::url($service->image_path) }}"
             alt="{{ $service->name }}"
             width="800"
             height="600"
             loading="lazy"
             class="h-full w-full object-cover">
      @else
        <span class="flex h-full w-full items-center justify-center font-[family-name:var(--font-heading-stack)] text-5xl font-bold"
              style="color: var(--brand-text)" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($service->name, 0, 1)) }}</span>
      @endif
    </div>
    <div class="grain" aria-hidden="true"></div>
  </div>

  <div class="flex flex-col flex-1">
    <div class="flex items-center justify-between gap-2 mb-1">
      {{-- What the buyer is booking against: when they will have it, or failing
           that, what the price is charged per. The unit itself rides on the price
           below, so it is never printed twice. --}}
      <p class="t-meta">{{ $service->turnaround_label ?: $service->billingUnit()->label() }}</p>
      <span class="text-xs text-[var(--ink-3)] font-medium">{{ $where ?: '—' }}</span>
    </div>

    <div class="mb-2 flex items-center justify-between gap-2">
      <h3 class="t-title text-[var(--ink)]">
        <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.service', ['slug' => $page->slug, 'serviceSlug' => $service->slug]) }}"
           class="card-stretched-link hover:underline">
          {{ $service->name }}
        </a>
      </h3>
      <x-themes.shared.verified-badge :page="$page" />
    </div>

    @if ($service->description)
      <p class="text-xs text-[var(--ink-2)] line-clamp-3 mb-4 leading-relaxed">
        {{ \Illuminate\Support\Str::limit($service->description, 220) }}
      </p>
    @endif

    @if ($deliverables !== [])
      <ul class="text-xs text-[var(--ink-2)] space-y-1 mb-4">
        @foreach (array_slice($deliverables, 0, 4) as $deliverable)
          <li class="flex items-center gap-1.5">
            <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
            <span>{{ $deliverable }}</span>
          </li>
        @endforeach

        @if (count($deliverables) > 4)
          <li class="text-[var(--ink-3)] font-medium pl-2.5">
            +{{ count($deliverables) - 4 }} more {{ \Illuminate\Support\Str::plural('deliverable', count($deliverables) - 4) }} included
          </li>
        @endif
      </ul>
    @endif

    <div class="grid grid-cols-2 gap-2 text-xs py-2.5 border-y border-[var(--rule)] mb-4">
      <div>
        <span class="text-[var(--ink-3)] block">Duration</span>
        <span class="font-semibold text-[var(--ink)]">{{ $service->durationLabel() }}</span>
      </div>
      <div>
        <span class="text-[var(--ink-3)] block">Deposit Required</span>
        <span class="font-semibold text-[var(--brand-text)]">
          @if ($service->hasDeposit())
            {{ \App\Shared\Support\Money::format($service->depositKobo()) }} (Deposit)
          @else
            None — paid in full
          @endif
        </span>
      </div>
    </div>

    <div class="mt-auto flex items-center justify-between">
      <div>
        <span class="text-[10px] text-[var(--ink-3)] block">Total Investment</span>
        <p class="t-price-sm">{{ $service->priceWithUnitLabel() }}</p>
      </div>

      {{-- Sits above the stretched link, so the card stays one click deep and the
           button is still a button. --}}
      <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.book', ['slug' => $page->slug, 'service' => $service->id]) }}"
         class="relative z-20 px-3.5 py-1.5 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] text-xs font-bold hover:opacity-90 transition-opacity">
        {{ $bookLabel }}
      </a>
    </div>
  </div>
</article>
