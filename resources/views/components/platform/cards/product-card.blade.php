@props([
    'product',
    'index' => 0,
])

@php
    $store = $product->storePage;
    $url = route('directory.go', ['type' => 'product', 'id' => $product->id, 'source' => 'directory']);
    $waUrl = route('directory.go', ['type' => 'whatsapp', 'id' => $store->id ?? 1, 'source' => 'directory']);
    $silhouette = $product->silhouette ?? 'tote';
    $reportUrl = $store ? route('directory.store', ['storePage' => $store, 'report' => 'product', 'id' => $product->id]).'#report' : null;
    $onSale = $product->isOnSale();
@endphp

<article data-reveal style="--stagger-index: {{ $index % 6 }}" class="platform-card-base group">
  <div class="platform-media-curtain relative aspect-[4/3] w-full overflow-hidden border-b border-[var(--platform-line)] bg-[var(--platform-surface)]">
    @if (!empty($product->primaryImageUrl))
      <img src="{{ $product->primaryImageUrl }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover">
    @else
      <x-platform.visual.placeholder :silhouette="$silhouette" :label="$product->category_name ?? 'Local Craft'" sublabel="Real Listing" />
    @endif

    @if ($onSale)
      <div class="absolute right-3 top-3 z-10 rounded-full bg-[var(--platform-ink)] px-2.5 py-1 text-[10px] font-bold tracking-wide text-[var(--platform-ink-invert)]">
        Sale −{{ $product->discountPercent() }}%
      </div>
    @endif

    {{-- Category Badge --}}
    @if (!empty($product->category_name))
      <div class="absolute left-3 top-3 rounded-full border border-[var(--platform-line)] bg-[var(--platform-paper)]/90 px-2.5 py-1 text-[10px] font-bold tracking-wide text-[var(--platform-ink)] backdrop-blur-xs">
        {{ $product->category_name }}
      </div>
    @endif
  </div>

  <div class="flex flex-1 flex-col p-5">
    <div class="flex items-center justify-between gap-2 text-xs text-[var(--platform-ink-muted)]">
      <span class="font-semibold truncate text-[var(--platform-brand)]">{{ $store->name ?? 'Verified Merchant' }}</span>
      <span class="shrink-0">{{ $store->city ?? 'Nigeria' }}</span>
    </div>

    <h3 class="mt-2 font-display text-lg font-bold leading-snug tracking-tight text-[var(--platform-ink)] group-hover:text-[var(--platform-brand)] transition-colors line-clamp-1">
      <a href="{{ $url }}">{{ $product->name }}</a>
    </h3>

    <p class="mt-2 text-xs leading-relaxed text-[var(--platform-ink-muted)] line-clamp-2">
      {{ strip_tags($product->description ?? 'Discover this item directly from the merchant on their sovereign store.') }}
    </p>

    @if ($reportUrl)
      <a href="{{ $reportUrl }}" class="mt-2 inline-block text-[10px] font-semibold text-[var(--platform-ink-muted)] hover:text-[var(--platform-ink)] hover:underline">Report this listing</a>
    @endif

    <div class="mt-auto pt-4 flex items-center justify-between gap-3 border-t border-[var(--platform-line)]">
      <div>
        <span class="block text-[10px] font-bold uppercase tracking-wider text-[var(--platform-ink-muted)]">Price</span>
        <span class="flex flex-wrap items-baseline gap-1.5 font-display text-base font-extrabold text-[var(--platform-ink)]">
          {{ $product->priceLabel ?? '₦'.number_format(($product->price_kobo ?? 0) / 100) }}
          @if ($onSale)
            <del class="text-[11px] font-semibold text-[var(--platform-ink-muted)] tabular-nums">{{ \App\Shared\Support\Money::format($product->originalPriceKobo()) }}</del>
          @endif
        </span>
      </div>

      <div class="flex items-center gap-1.5">
        <a href="{{ $waUrl }}" title="Chat on WhatsApp" class="inline-flex size-9 items-center justify-center rounded-xl border border-[var(--platform-line)] text-[var(--platform-brand)] hover:bg-[var(--platform-brand-wash)] transition-colors">
          <x-ui.icon name="whatsapp" class="size-4" />
        </a>
        <a href="{{ $url }}" class="inline-flex h-9 items-center gap-1 rounded-xl bg-[var(--platform-brand)] px-3 text-xs font-bold text-[var(--platform-ink-invert)] hover:bg-[var(--platform-brand-hover)] transition-all">
          <span>View on store</span>
          <svg class="size-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
    </div>
  </div>
</article>
