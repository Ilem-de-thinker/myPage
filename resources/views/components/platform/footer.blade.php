@props([
    'demoStore' => null,
    'demoService' => null,
])

@php
    use App\Domains\Admin\Models\PlatformSetting;
    use App\Domains\Billing\Services\Feature;
    use App\Shared\Support\PlatformNetwork;

    $showDiscovery = Feature::enabled('discovery');
    $showDirectory = Feature::enabled('directory');

    // The cities the platform actually operates in, not a fixed list: a claim
    // about where mypage.ng has merchants has to be checkable.
    $cities = PlatformNetwork::cities(4);
    $platformName = PlatformSetting::value('platform_name', 'mypage.ng');
    $platformTagline = PlatformSetting::value('platform_tagline', 'A discovery directory for Nigerian stores, products and services.');
@endphp

<footer class="border-t border-[var(--rule)] bg-[var(--surface)] text-xs text-[var(--ink-2)] py-14">
    <div class="shell">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        <div class="space-y-3">
          <a href="{{ route('home') }}" class="flex items-center gap-2 text-base font-extrabold tracking-tight text-[var(--ink)]">
            <span style="font-family: var(--font-heading)">{{ $platformName }}</span>
          </a>
          <p class="t-body text-xs text-[var(--ink-3)] leading-relaxed t-measure-narrow">{{ $platformTagline }}</p>
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
              <li><a href="{{ route('directory.index') }}" class="hover:text-[var(--ink)] transition-colors">Browse directory</a></li>
            @endif
            @if ($demoStore)
              <li><a href="{{ \App\Shared\Support\StoreUrl::homeForSlug($demoStore) }}" class="hover:text-[var(--ink)] transition-colors">Retail Store Demo</a></li>
            @endif
            @if ($demoService)
              <li><a href="{{ \App\Shared\Support\StoreUrl::homeForSlug($demoService) }}" class="hover:text-[var(--ink)] transition-colors">Booking Service Demo</a></li>
            @endif
            <li><a href="{{ route('design-system') }}" class="hover:text-[var(--ink)] transition-colors">Living Style Guide</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-xs font-bold text-[var(--ink)] mb-3">For merchants</h4>
          <ul class="space-y-2 text-xs">
            <li><a href="{{ route('register') }}" class="hover:text-[var(--ink)] transition-colors">Create your store</a></li>
            <li><a href="{{ route('home') }}#for-merchants" class="hover:text-[var(--ink)] transition-colors">Why list with MyPage</a></li>
            <li><a href="{{ route('home') }}#pricing" class="hover:text-[var(--ink)] transition-colors">Merchant plans</a></li>
          </ul>
        </div>
        <div>
          <h4 class="text-xs font-bold text-[var(--ink)] mb-3">A clear handoff</h4>
          <p class="text-xs text-[var(--ink-3)] leading-relaxed mb-3">
            MyPage helps you discover a merchant. You then continue on the merchant’s own store, where they handle questions, bookings, orders and payment.
          </p>
          <div class="inline-flex items-center gap-2 rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--paper)] px-3 py-1.5 text-[11px] font-semibold text-[var(--brand-text)]">
            <x-ui.icon name="arrow-right" class="size-3.5" />
            <span>Direct to the seller</span>
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
          <a href="{{ route('legal.policy', 'privacy') }}" class="hover:text-[var(--ink)] transition-colors">Privacy Policy</a>
          <a href="{{ route('legal.policy', 'terms') }}" class="hover:text-[var(--ink)] transition-colors">Merchant Terms</a>
          <a href="{{ route('legal.policy', 'contact') }}" class="hover:text-[var(--ink)] transition-colors">Contact</a>
        </div>
      </div>
    </div>
</footer>
