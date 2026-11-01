@props(['store' => null])

@php
    use App\Domains\Seller\Models\StorePage;
    use App\Shared\Support\PricingPlans;

    $plans = PricingPlans::cards();
    $publishedStores = StorePage::query()->discoverable()->count();
    $statesRepresented = StorePage::query()
        ->discoverable()
        ->whereNotNull('state')
        ->distinct()
        ->count('state');
    $citiesRepresented = StorePage::query()
        ->discoverable()
        ->whereNotNull('city')
        ->distinct()
        ->count('city');

    $previewName = $store?->name;
    $previewTheme = $store?->theme?->value;
    $previewColour = $store?->brand_color;
@endphp

<section id="for-merchants" class="relative overflow-hidden border-y border-[var(--platform-line)] bg-[var(--platform-ink)] py-20 text-[var(--platform-ink-invert)] lg:py-28">
  {{-- Section texture --}}
  <div class="pointer-events-none absolute inset-0" aria-hidden="true">
    <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle, var(--platform-ink-invert) 1px, transparent 1px); background-size: 24px 24px;"></div>
    <div class="absolute -left-24 top-16 size-[28rem] rounded-full bg-[var(--platform-support)]/20 blur-3xl"></div>
    <div class="absolute -right-20 bottom-24 size-[24rem] rounded-full bg-[var(--platform-accent)]/16 blur-3xl"></div>
    <div class="absolute -right-8 top-20 font-display text-[clamp(6rem,18vw,18rem)] font-extrabold leading-none tracking-[-0.08em] text-transparent opacity-25" style="-webkit-text-stroke: 1px color-mix(in srgb, var(--platform-ink-invert) 18%, transparent);">OWN IT</div>
    <svg class="absolute bottom-10 left-6 hidden h-40 w-[26rem] opacity-40 xl:block" viewBox="0 0 480 180" fill="none">
      <path d="M6 154 C 110 154, 140 20, 260 20 S 390 130, 474 54" stroke="var(--platform-support)" stroke-width="1.5" stroke-dasharray="5 8" />
      <circle cx="6" cy="154" r="5" fill="var(--platform-accent)" />
      <circle cx="474" cy="54" r="6" fill="var(--platform-support)" />
    </svg>
  </div>

  <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-8">
    <div class="max-w-3xl" data-reveal>
      <span class="inline-flex items-center gap-2 rounded-full border border-[var(--platform-support-border)] bg-[var(--platform-support)]/12 px-3.5 py-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--platform-support-on-ink)]">
        For independent Nigerian merchants
      </span>
      <h2 class="mt-5 font-display text-[clamp(2.5rem,6vw,5.25rem)] font-extrabold leading-[.94] tracking-[var(--platform-tracking-display)]">
        Your own store.<br>
        <span class="font-normal italic text-[var(--platform-accent-on-ink)]">Your own customer.</span>
      </h2>
      <p class="mt-6 max-w-2xl text-base leading-8 text-[color-mix(in_srgb,var(--platform-ink-invert)_62%,transparent)] sm:text-lg">
        Publish a storefront, list what you make, and send buyers straight to WhatsApp or your own checkout. MyPage introduces you; the relationship stays yours.
      </p>
    </div>

    {{-- Facts from the live directory, not invented scale. --}}
    <div class="mt-14 grid grid-cols-2 gap-x-6 gap-y-8 border-y border-[color-mix(in_srgb,var(--platform-ink-invert)_12%,transparent)] py-9 sm:grid-cols-4">
      <div data-reveal>
        <span class="font-display text-4xl font-extrabold text-[var(--platform-ink-invert)] sm:text-5xl" data-counter="{{ $publishedStores }}">{{ number_format($publishedStores) }}</span>
        <span class="mt-2 block text-[10px] font-bold uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">Published storefronts</span>
      </div>
      <div data-reveal style="--stagger-index: 1">
        <span class="font-display text-4xl font-extrabold text-[var(--platform-accent-on-ink)] sm:text-5xl">₦0</span>
        <span class="mt-2 block text-[10px] font-bold uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">Platform commission</span>
      </div>
      <div data-reveal style="--stagger-index: 2">
        <span class="font-display text-4xl font-extrabold text-[var(--platform-ink-invert)] sm:text-5xl" data-counter="{{ $statesRepresented }}">{{ number_format($statesRepresented) }}</span>
        <span class="mt-2 block text-[10px] font-bold uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">States represented</span>
      </div>
      <div data-reveal style="--stagger-index: 3">
        <span class="font-display text-4xl font-extrabold text-[var(--platform-support-on-ink)] sm:text-5xl" data-counter="{{ $citiesRepresented }}">{{ number_format($citiesRepresented) }}</span>
        <span class="mt-2 block text-[10px] font-bold uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">Cities with a store</span>
      </div>
    </div>

    <div class="mt-20 grid items-center gap-12 lg:grid-cols-[1fr_1fr] lg:gap-16">
      <div data-reveal>
        <span class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--platform-accent-on-ink)]">A storefront, not a marketplace listing</span>
        <h3 class="mt-3 max-w-lg font-display text-3xl font-bold leading-tight sm:text-4xl">
          Launch with your catalogue, colours and domain.
        </h3>
        <p class="mt-4 max-w-lg text-sm leading-7 text-[color-mix(in_srgb,var(--platform-ink-invert)_60%,transparent)]">
          Add your products or services, choose a theme, connect your payment gateway and publish. The directory only shows stores that have opted in.
        </p>

        <ul class="mt-7 grid gap-3 text-sm text-[color-mix(in_srgb,var(--platform-ink-invert)_70%,transparent)]">
          @foreach ([
              'Custom domain or yourname.mypage.ng',
              'Products, services and appointment bookings',
              'WhatsApp order forwarding and direct payment',
              'Directory opt-in you control from your dashboard',
          ] as $feature)
            <li class="flex items-start gap-2.5">
              <svg class="mt-0.5 size-4 shrink-0 text-[var(--platform-support-on-ink)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
              <span>{{ $feature }}</span>
            </li>
          @endforeach
        </ul>

        <a href="{{ route('register') }}" class="btn-magnetic mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[var(--platform-accent)] px-7 text-sm font-extrabold text-[var(--platform-ink-invert)] shadow-[var(--platform-shadow-accent)] transition hover:bg-[var(--platform-accent-hover)]">
          <span>Claim your store handle</span>
          <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>

      {{-- Storefront setup preview. Shows the merchant's data when available and
           stays neutral when the directory is empty. --}}
      <div data-reveal="scale" class="rounded-[var(--platform-radius-2xl)] border border-[color-mix(in_srgb,var(--platform-ink-invert)_15%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_8%,transparent)] p-4 shadow-2xl backdrop-blur-sm sm:p-6">
        <div class="flex items-center justify-between border-b border-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] pb-4">
          <span class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--platform-ink-invert)_50%,transparent)]">Storefront preview</span>
          <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-[var(--platform-support-on-ink)]">
            <span class="size-1.5 rounded-full bg-[var(--platform-support-on-ink)]"></span>
            Ready to configure
          </span>
        </div>

        <div class="mt-5 grid gap-4">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-[color-mix(in_srgb,var(--platform-ink-invert)_40%,transparent)]">Business name</span>
            <div class="mt-1.5 rounded-xl border border-[color-mix(in_srgb,var(--platform-ink-invert)_15%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_5%,transparent)] p-3 font-display text-lg text-[var(--platform-ink-invert)]">
              {{ $previewName ?: 'Your business name' }}
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-[color-mix(in_srgb,var(--platform-ink-invert)_40%,transparent)]">Storefront type</span>
              <div class="mt-1.5 rounded-xl border border-[color-mix(in_srgb,var(--platform-ink-invert)_15%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_5%,transparent)] p-3 text-sm text-[color-mix(in_srgb,var(--platform-ink-invert)_70%,transparent)]">
                {{ $previewTheme ? \Illuminate\Support\Str::headline($previewTheme) : 'Product or service' }}
              </div>
            </div>
            <div>
              <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-[color-mix(in_srgb,var(--platform-ink-invert)_40%,transparent)]">Directory</span>
              <div class="mt-1.5 rounded-xl border border-[color-mix(in_srgb,var(--platform-ink-invert)_15%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_5%,transparent)] p-3 text-sm text-[color-mix(in_srgb,var(--platform-ink-invert)_70%,transparent)]">
                Opt-in after publishing
              </div>
            </div>
          </div>

          <div class="rounded-xl border border-[var(--platform-support-border)] bg-[var(--platform-support)]/10 p-4">
            <div class="flex items-center justify-between gap-4">
              <div>
                <span class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[var(--platform-support-on-ink)]">Your storefront</span>
                <p class="mt-1 text-sm font-semibold text-[var(--platform-ink-invert)]">{{ $previewName ?: 'Published store name' }}</p>
              </div>
              <span class="size-3.5 rounded-full border border-[color-mix(in_srgb,var(--platform-ink-invert)_30%,transparent)]" style="background: {{ $previewColour ?: 'var(--platform-accent)' }}"></span>
            </div>
            <p class="mt-3 border-t border-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] pt-3 text-xs leading-5 text-[color-mix(in_srgb,var(--platform-ink-invert)_55%,transparent)]">
              Buyers see your catalogue, location and contact route. Payment and fulfilment stay in your hands.
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- Pricing from the same plan rows the billing flow uses. --}}
    <div id="pricing" class="mt-24 scroll-mt-24 border-t border-[color-mix(in_srgb,var(--platform-ink-invert)_12%,transparent)] pt-16">
      <div data-reveal class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
          <span class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[var(--platform-accent-on-ink)]">Transparent subscription</span>
          <h3 class="mt-3 font-display text-3xl font-bold leading-tight sm:text-4xl">Flat plans. No transaction tax.</h3>
        </div>
        <p class="max-w-sm text-xs leading-6 text-[color-mix(in_srgb,var(--platform-ink-invert)_50%,transparent)]">
          Prices below are read from the active platform plans. Customer payments go directly to your own payment account.
        </p>
      </div>

      @if ($plans !== [])
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($plans as $index => $plan)
            <article data-reveal style="--stagger-index: {{ $index }}" @class([
                'relative flex flex-col justify-between rounded-[var(--platform-radius-xl)] border p-6 sm:p-7',
                'border-[var(--platform-accent)] bg-[var(--platform-accent)]/8 shadow-[var(--platform-shadow-accent)]' => $plan['featured'],
                'border-[color-mix(in_srgb,var(--platform-ink-invert)_12%,transparent)] bg-[color-mix(in_srgb,var(--platform-ink-invert)_3.5%,transparent)]' => ! $plan['featured'],
            ])>
              @if ($plan['featured'])
                <span class="absolute -top-3 right-5 rounded-full bg-[var(--platform-accent)] px-3 py-1 text-[9px] font-extrabold uppercase tracking-[0.12em] text-[var(--platform-ink-invert)]">Featured</span>
              @endif
              <div>
                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">{{ $plan['tagline'] }}</span>
                <h4 class="mt-2 font-display text-xl font-bold text-[var(--platform-ink-invert)]">{{ $plan['name'] }}</h4>
                <div class="mt-4 flex items-baseline gap-1">
                  <span class="font-display text-4xl font-extrabold text-[var(--platform-ink-invert)]">{{ $plan['price'] }}</span>
                  <span class="text-xs text-[color-mix(in_srgb,var(--platform-ink-invert)_45%,transparent)]">/ month</span>
                </div>
                <p class="mt-3 text-xs leading-6 text-[color-mix(in_srgb,var(--platform-ink-invert)_55%,transparent)]">{{ $plan['description'] }}</p>
                <ul class="mt-5 grid gap-2.5 border-t border-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] pt-5 text-xs leading-5 text-[color-mix(in_srgb,var(--platform-ink-invert)_65%,transparent)]">
                  @foreach ($plan['features'] as $feature)
                    <li @class(['flex items-start gap-2', 'text-[color-mix(in_srgb,var(--platform-ink-invert)_35%,transparent)]' => $feature['muted'] ?? false])>
                      <svg class="mt-0.5 size-3.5 shrink-0 text-[var(--platform-support-on-ink)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                      <span>
                        {{ $feature['label'] }}
                        @if (! empty($feature['code']))
                          <code class="ml-1 rounded bg-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)] px-1.5 py-0.5 text-[10px] text-[color-mix(in_srgb,var(--platform-ink-invert)_70%,transparent)]">{{ $feature['code'] }}</code>
                        @endif
                      </span>
                    </li>
                  @endforeach
                </ul>
              </div>
              <a href="{{ route('register') }}" @class([
                  'mt-7 inline-flex h-11 items-center justify-center rounded-xl text-xs font-extrabold transition',
                  'bg-[var(--platform-accent)] text-[var(--platform-ink-invert)] hover:bg-[var(--platform-accent-hover)]' => $plan['featured'],
                  'border border-[color-mix(in_srgb,var(--platform-ink-invert)_20%,transparent)] text-[var(--platform-ink-invert)] hover:bg-[color-mix(in_srgb,var(--platform-ink-invert)_10%,transparent)]' => ! $plan['featured'],
              ])>
                {{ $plan['featured'] ? 'Start with this plan' : 'Choose this plan' }}
              </a>
            </article>
          @endforeach
        </div>
      @else
        <div class="mt-10 rounded-[var(--platform-radius-xl)] border border-dashed border-[color-mix(in_srgb,var(--platform-ink-invert)_20%,transparent)] p-8 text-center">
          <p class="text-sm text-[color-mix(in_srgb,var(--platform-ink-invert)_60%,transparent)]">Active plans are being configured. Create your account to see the current pricing.</p>
          <a href="{{ route('register') }}" class="mt-4 inline-flex h-11 items-center rounded-xl bg-[var(--platform-accent)] px-6 text-xs font-extrabold text-[var(--platform-ink-invert)]">Create your store</a>
        </div>
      @endif
    </div>
  </div>
</section>
