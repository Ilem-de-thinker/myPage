@props([
    'demoStore' => null,
    'demoService' => null,
])

@php
    use App\Domains\Billing\Services\Feature;
    use App\Shared\Support\PlatformNetwork;

    $showDiscovery = Feature::enabled('discovery');
    $showDirectory = Feature::enabled('directory');
    $storefrontParams = static fn (?string $slug): array => $slug ? ['slug' => $slug] : [];

    // The cities the platform actually operates in, not a fixed list: a claim
    // about where mypage.ng has merchants has to be checkable.
    $cities = PlatformNetwork::cities(4);
@endphp

<footer class="border-t border-[var(--rule)] bg-[var(--surface)] text-xs text-[var(--ink-2)] py-14">
    <div class="shell">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        <div class="space-y-3">
          <a href="{{ route('home') }}" class="flex items-center gap-0.5 text-base font-extrabold tracking-tight text-[var(--ink)]">
            <span style="font-family: var(--font-heading)">mypage</span><span class="text-[var(--brand)]">.ng</span>
          </a>
          <p class="t-body text-xs text-[var(--ink-3)] leading-relaxed t-measure-narrow">
            The sovereign multi-tenant storefront infrastructure for verified Nigerian enterprises, designers, and artisans.
          </p>
          @if ($cities->isNotEmpty())
            <p class="t-meta">{{ $cities->join(' · ') }}</p>
          @endif
        </div>
        <div>
          <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Explore Platform</h4>
          <ul class="space-y-2 text-xs">
            @if ($showDiscovery)
              <li><a href="{{ route('discovery.index') }}" class="hover:text-[var(--ink)] transition-colors">Discover Merchants</a></li>
            @endif
            @if ($showDirectory)
              <li><a href="{{ route('directory.index') }}" class="hover:text-[var(--ink)] transition-colors">Legacy Directory</a></li>
            @endif
            @if ($demoStore)
              <li><a href="{{ route('storefront.show', $storefrontParams($demoStore)) }}" class="hover:text-[var(--ink)] transition-colors">Retail Store Demo</a></li>
            @endif
            @if ($demoService)
              <li><a href="{{ route('storefront.show', $storefrontParams($demoService)) }}" class="hover:text-[var(--ink)] transition-colors">Booking Service Demo</a></li>
            @endif
            <li><a href="{{ route('design-system') }}" class="hover:text-[var(--ink)] transition-colors">Living Style Guide</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Commerce Infrastructure</h4>
          <ul class="space-y-2 text-xs">
            {{--
                Escrow is reached through a buyer's signed order link, never from a
                public URL, so there is no page here to point at — the protocol is
                explained in the FAQ above instead of linked.
            --}}
            <li><span class="text-[var(--ink-3)]">Escrow Protection Protocol</span></li>
            @if ($demoStore)
              <li><a href="{{ route('storefront.track-order', $storefrontParams($demoStore)) }}" class="hover:text-[var(--ink)] transition-colors">Nationwide Waybill Tracking</a></li>
            @else
              <li><span class="text-[var(--ink-3)]">Nationwide Waybill Tracking</span></li>
            @endif
            <li><a href="{{ route('home') }}#how-it-works" class="hover:text-[var(--ink)] transition-colors">WhatsApp Order Automation</a></li>
            <li><a href="{{ route('home') }}#pricing" class="hover:text-[var(--ink)] transition-colors">Merchant Plans &amp; Fees</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Buyer Trust &amp; Escrow</h4>
          <p class="text-xs text-[var(--ink-3)] leading-relaxed mb-3">
            Buyers never need an account. Every transaction is backed by bank-grade escrow confirmation and seller dispute protection.
          </p>
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] text-[11px] font-semibold text-[var(--brand-text)]">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Verified Merchant Guarantee</span>
          </div>
        </div>
      </div>
      <div class="pt-8 border-t border-[var(--rule)] flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-[var(--ink-3)]">
        <p>© {{ now()->year }} mypage.ng. Built for Nigerian commerce. All rights reserved.</p>
        <div class="flex items-center gap-6">
          {{--
              A signed-in seller's way back to their own shop. Buyers never get
              one — the door only appears for somebody who already has a page.
          --}}
          @auth
            <a href="{{ route('dashboard') }}" class="hover:text-[var(--ink)] transition-colors">Your dashboard</a>
          @endauth
          <a href="{{ route('design-system') }}" class="hover:text-[var(--ink)] transition-colors">Design System Tokens</a>
          <a href="#privacy" class="hover:text-[var(--ink)] transition-colors">Privacy Policy</a>
          <a href="#terms" class="hover:text-[var(--ink)] transition-colors">Merchant Terms</a>
        </div>
      </div>
    </div>
</footer>