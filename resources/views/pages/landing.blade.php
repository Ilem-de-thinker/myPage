@php
    use App\Shared\Support\DemoStorefronts;
    use App\Shared\Support\PricingPlans;

    $serviceDemo = DemoStorefronts::service();
    $storeDemo = DemoStorefronts::store();
@endphp

@extends('layouts.platform')

@section('title', 'mypage.ng — Sovereign Storefronts for Nigerian Enterprise')
@section('description', 'Multi-tenant storefront platform for Nigerian businesses — products, services, bookings, and verified commerce.')
@section('body-state', '{ annualPricing: false, mobileNavOpen: false }')

@section('content')

    <!-- HERO SECTION -->
    <section class="section-y relative overflow-hidden bg-gradient-to-b from-[var(--surface)] via-[var(--paper)] to-[var(--paper)]">
      <!-- Ambient Wash Accents -->
      <div class="absolute -top-32 left-1/4 w-[36rem] h-[36rem] rounded-full bg-[var(--brand-wash-2)] blur-3xl pointer-events-none -z-10" aria-hidden="true"></div>
      <div class="absolute top-20 right-10 w-[28rem] h-[28rem] rounded-full bg-amber-500/5 blur-3xl pointer-events-none -z-10" aria-hidden="true"></div>

      <div class="shell">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

          <!-- Hero Copy (7 Columns) -->
          <div class="lg:col-span-7 space-y-6">

            <h1 class="t-display-1 text-[var(--ink)]">
              Storefronts for businesses worth ₦1.5 billion.
            </h1>

            <p class="t-body-lg text-[var(--ink-2)] t-measure">
              Convert high-intent Instagram and TikTok attention into verified orders. Direct WhatsApp checkout, automated Nigerian bank transfers, and built-in escrow protection — without platform lock-in.
            </p>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
              <a href="{{ route('register') }}" class="h-12 px-6 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-sm flex items-center justify-center gap-2 shadow-sm hover:opacity-90 transition-opacity">
                <span>Claim Your Storefront Handle</span>
                <span aria-hidden="true">→</span>
              </a>
              @if (\App\Domains\Billing\Services\Feature::enabled('discovery'))
                <a href="{{ route('discovery.index') }}" class="h-12 px-5 rounded-[var(--radius)] bg-[var(--card)] text-[var(--ink)] border border-[var(--rule)] font-semibold text-sm flex items-center justify-center hover:border-[var(--brand-edge)] transition-colors">
                  Browse Verified Merchants
                </a>
              @endif
            </div>

            <!-- PROMINENT DEMO ACCESS CARD -->
            @if ($serviceDemo || $storeDemo)
              <div class="p-4 rounded-[var(--radius)] bg-[var(--card)] border border-[var(--rule)] shadow-sm space-y-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-[var(--ink)] flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Experience Both Production Engines:
                  </span>
                  <span class="t-meta">Live &amp; Fully Interactive</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                  @if ($serviceDemo)
                    <a href="{{ $serviceDemo['href'] }}" class="p-3 rounded-[var(--radius-sm)] bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 transition-all flex items-center justify-between group">
                      <div>
                        <span class="font-extrabold text-amber-900 dark:text-amber-100 flex items-center gap-1.5">
                          <span>📅 Service Booking Demo</span>
                        </span>
                        <span class="t-meta block mt-0.5">{{ $serviceDemo['name'] }} · {{ $serviceDemo['location'] }} (Appointments &amp; Escrow)</span>
                      </div>
                      <span class="font-bold text-amber-700 dark:text-amber-300 group-hover:translate-x-1 transition-transform ml-2">&rarr;</span>
                    </a>
                  @endif

                  @if ($storeDemo)
                    <a href="{{ $storeDemo['href'] }}" class="p-3 rounded-[var(--radius-sm)] bg-[var(--surface)] hover:bg-[var(--brand-wash)] border border-[var(--rule)] hover:border-[var(--brand-edge)] transition-all flex items-center justify-between group">
                      <div>
                        <span class="font-extrabold text-[var(--ink)] flex items-center gap-1.5">
                          <span>🛍️ Retail Store Demo</span>
                        </span>
                        <span class="t-meta block mt-0.5">{{ $storeDemo['name'] }} · {{ $storeDemo['location'] }} (Bag &amp; Escrow)</span>
                      </div>
                      <span class="font-bold text-[var(--brand-text)] group-hover:translate-x-1 transition-transform ml-2">&rarr;</span>
                    </a>
                  @endif
                </div>
              </div>
            @endif

            <!-- Reassurance Note -->
            <div class="pt-2 flex items-center gap-3 text-xs text-[var(--ink-3)]">
              <svg class="w-4 h-4 text-[var(--brand)] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
              <span>Buyers never need an account · 0% transaction fee on direct bank transfers · Instant WhatsApp handoff</span>
            </div>
          </div>

          <!-- Composed Product Visual with Live Interactive Switcher (5 Columns) -->
          @if ($serviceDemo || $storeDemo)
            <x-platform.demo-frame :service="$serviceDemo" :store="$storeDemo" />
          @endif

        </div>
      </div>
    </section>

    <!-- TWO-THEME EXPLAINER: STORE VS SERVICE -->
    <section id="themes" class="section-y border-t border-[var(--rule)] bg-[var(--surface)]">
      <div class="shell">
        <div class="text-center max-w-2xl mx-auto mb-16">
          <p class="t-meta">Two Dedicated Engines</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Tailored for physical goods or appointments</h2>
          <p class="t-body text-[var(--ink-2)] mt-3">
            Never force a service business into a retail shopping cart, or a fashion brand into an appointment form.
            Pick the architecture engineered for your exact business model.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

          {{--
              Theme 1: Retail Storefront.

              The card always renders — it is how a visitor chooses a theme, and a
              choice that disappears when the demo data is missing is not a
              choice. Only the sample inside it needs a live storefront to read
              from.
          --}}
          @php $storeLive = \App\Shared\Support\PlatformNetwork::publishedCount(\App\Domains\Seller\Enums\Theme::Store); @endphp
              <x-platform.theme-card
                  variant="store"
                  title="Physical Goods, Fashion &amp; Artisanal Craft"
                  body="Engineered for fashion designers, bakeries, leathercraft guilds, and electronics merchants. Supports size variants, inventory counters, live cart drawers, and direct WhatsApp invoice payloads."
                  :demo-href="$storeDemo['href'] ?? route('register')"
                  :demo-label="$storeDemo ? 'Explore Demo Store' : 'Start a Store'"
                  :features="[
                      'Automated inventory decrement with out-of-stock guards',
                      'Integrated courier zones (Lagos Island, Mainland, Interstate)',
                      'Escrow release trigger on customer delivery confirmation',
                  ]">
                <x-slot:preview>
                  <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] space-y-3">
                    @if ($storeDemo)
                      <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-[var(--ink)] truncate">{{ $storeDemo['listing'] }}</span>
                        <span class="t-price-sm">{{ $storeDemo['price'] }}</span>
                      </div>
                      <div class="flex gap-2">
                        <span class="px-2.5 py-1 rounded bg-[var(--paper)] border border-[var(--rule)] text-[11px] font-semibold">{{ $storeDemo['name'] }}</span>
                        <span class="px-2.5 py-1 rounded bg-[var(--brand-wash)] border border-[var(--brand-edge)] text-[var(--brand-text)] text-[11px] font-bold">{{ $storeDemo['meta'] }}</span>
                      </div>
                      <div class="pt-2 border-t border-[var(--rule)] flex items-center justify-between text-[11px]">
                        <span class="text-[var(--ink-3)]">WhatsApp Order Cart with Waybill</span>
                        <span class="font-bold text-[var(--brand-text)]">{{ $storeDemo['location'] }}</span>
                      </div>
                    @else
                      {{-- No seeded demo: the same panel, showing the platform's own
                           count rather than an invented product. --}}
                      <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-[var(--ink)]">Physical goods, sold one by one</span>
                        <span class="t-price-sm">{{ number_format($storeLive) }} {{ $storeLive === 1 ? 'store' : 'stores' }}</span>
                      </div>
                      <div class="flex gap-2">
                        <span class="px-2.5 py-1 rounded bg-[var(--paper)] border border-[var(--rule)] text-[11px] font-semibold">Inventory &amp; variants</span>
                        <span class="px-2.5 py-1 rounded bg-[var(--brand-wash)] border border-[var(--brand-edge)] text-[var(--brand-text)] text-[11px] font-bold">Escrow checkout</span>
                      </div>
                      <div class="pt-2 border-t border-[var(--rule)] flex items-center justify-between text-[11px]">
                        <span class="text-[var(--ink-3)]">WhatsApp Order Cart with Waybill</span>
                        <span class="font-bold text-[var(--brand-text)]">Direct bank transfer</span>
                      </div>
                    @endif
                  </div>
                </x-slot:preview>
              </x-platform.theme-card>

          {{-- Theme 2: Service Booking --}}
          @php $serviceLive = \App\Shared\Support\PlatformNetwork::publishedCount(\App\Domains\Seller\Enums\Theme::Service); @endphp
              <x-platform.theme-card
                  variant="service"
                  title="Studios, Photographers, Salons &amp; Consultants"
                  body="Engineered for creative directors, makeup artists, bridal gele specialists, and commercial studios. Direct month-grid calendar slots, custom deposit rules, and automated reminders."
                  :demo-href="$serviceDemo['href'] ?? route('register')"
                  :demo-label="$serviceDemo ? 'Explore Demo Service' : 'Start a Booking Page'"
                  :features="[
                      'Configurable deposit percentages (30% or 50% upfront)',
                      'Automated WhatsApp reminder 24 hours prior to appointment',
                      'Buffer times and weekly client intake caps',
                  ]">
                <x-slot:preview>
                  <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] space-y-3">
                    @if ($serviceDemo)
                      <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-[var(--ink)] truncate">{{ $serviceDemo['listing'] }}</span>
                        <span class="t-price-sm">{{ $serviceDemo['price'] }}</span>
                      </div>
                      <div class="flex gap-2">
                        <span class="px-2.5 py-1 rounded bg-[var(--paper)] border border-[var(--rule)] text-[11px] font-semibold">{{ $serviceDemo['meta'] }}</span>
                        @if ($serviceDemo['detail'])
                          <span class="px-2.5 py-1 rounded bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-[11px] font-bold">{{ $serviceDemo['detail'] }}</span>
                        @endif
                      </div>
                      <div class="pt-2 border-t border-[var(--rule)] flex items-center justify-between text-[11px]">
                        <span class="text-[var(--ink-3)]">No ghost bookings · Upfront commitment</span>
                        <span class="font-bold text-[var(--brand-text)]">{{ $serviceDemo['location'] }}</span>
                      </div>
                    @else
                      {{-- Same panel without a seeded studio to quote. --}}
                      <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-[var(--ink)]">Appointments, held to the minute</span>
                        <span class="t-price-sm">{{ number_format($serviceLive) }} {{ $serviceLive === 1 ? 'studio' : 'studios' }}</span>
                      </div>
                      <div class="flex gap-2">
                        <span class="px-2.5 py-1 rounded bg-[var(--paper)] border border-[var(--rule)] text-[11px] font-semibold">Month-grid calendar</span>
                        <span class="px-2.5 py-1 rounded bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-[11px] font-bold">Upfront deposit</span>
                      </div>
                      <div class="pt-2 border-t border-[var(--rule)] flex items-center justify-between text-[11px]">
                        <span class="text-[var(--ink-3)]">No ghost bookings · Upfront commitment</span>
                        <span class="font-bold text-[var(--brand-text)]">Calendar synced</span>
                      </div>
                    @endif
                  </div>
                </x-slot:preview>
              </x-platform.theme-card>

        </div>
      </div>
    </section>

    <!-- HOW IT WORKS (3 NUMBERED STEPS IN AN <ol>) -->
    <section id="how-it-works" class="section-y border-t border-[var(--rule)]">
      <div class="shell">
        <div class="text-center max-w-2xl mx-auto mb-16">
          <p class="t-meta">Simple Sovereign Architecture</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">How mypage works in three steps</h2>
          <p class="t-body text-[var(--ink-2)] mt-3">
            Designed for the Nigerian reality: low bandwidth friction, zero forced sign-ups for buyers, and ironclad payment assurance.
          </p>
        </div>

        <ol class="grid grid-cols-1 md:grid-cols-3 gap-8 list-none p-0">

          <x-platform.how-step number="01" title="Claim your handle in 60 seconds">
            Register <code class="font-mono bg-[var(--paper)] px-1.5 py-0.5 rounded border border-[var(--rule)]">mypage.ng/yourbrand</code>. No credit card required. Upload your logo, specify your WhatsApp dispatch number, and select your signature brand tint.
          </x-platform.how-step>

          <x-platform.how-step number="02" title="Publish products or booking calendar">
            List inventory with size variants and high-res photography, or activate your appointment calendar with custom time slots and required upfront commitment deposits in Nigerian Naira (₦).
          </x-platform.how-step>

          <x-platform.how-step number="03" title="Get paid with instant escrow security">
            Buyers purchase via direct bank transfer or WhatsApp with zero password friction. Payments are locked in sovereign escrow until goods are dispatched, eliminating fraud for both buyer and seller.
          </x-platform.how-step>

        </ol>
      </div>
    </section>

    {{-- SOCIAL PROOF STRIP: real merchants, read from the database --}}
    <x-platform.logo-strip :merchants="\App\Shared\Support\PlatformNetwork::merchants()" />

    <!-- PRICING SECTION (3 PLANS; FEATURED = BRAND BORDER + MOST POPULAR BADGE) -->
    <section id="pricing" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="text-center max-w-2xl mx-auto mb-16">
          <p class="t-meta">Predictable Merchant Pricing</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Transparent fees, zero surprise cuts</h2>
          <p class="t-body text-[var(--ink-2)] mt-3">
            Keep your hard-earned margins. No percentage gouging on direct WhatsApp transactions.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">

            {{--
                The figures are the platform's, not the design's: they come from
                the same Plan rows the checkout reads, so a price edited in admin
                shows here. Only the card around them is the design's.
            --}}
            @foreach (PricingPlans::cards() as $plan)
                <x-platform.pricing-plan
                    :name="$plan['name']"
                    :tagline="$plan['tagline']"
                    :price="$plan['price']"
                    :description="$plan['description']"
                    :features="$plan['features']"
                    :featured="$plan['featured']"
                    :badge="$plan['featured'] ? 'Most Popular' : null"
                    :cta-label="$plan['featured'] ? 'Activate '.$plan['name'].' →' : 'Start with '.$plan['name']"
                    :cta-href="route('register')" />
            @endforeach

        </div>

        @unless (PricingPlans::fromDatabase())
            {{--
                Says where the numbers came from. A price a visitor cannot
                actually buy from is worse than no price at all.
            --}}
            <p class="t-meta text-center mt-8">Showing the configured prices</p>
        @endunless
      </div>
    </section>

    <!-- FAQ ACCORDION (NATIVE <details>) -->
    <section class="section-y border-b border-[var(--rule)] bg-[var(--surface)]">
      <div class="shell max-w-3xl">
        <div class="text-center mb-12">
          <p class="t-meta">Everything You Need to Know</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Frequently asked questions</h2>
        </div>

        <div class="space-y-4">

          <x-platform.faq-item question="Do my buyers need to download an app or register an account?">
            Never. Nigerian consumers abandon checkouts when forced to create passwords or download separate apps. Your mypage storefront opens instantly in mobile web browsers, and checkouts route seamlessly via direct WhatsApp message or instant bank transfer.
          </x-platform.faq-item>

          <x-platform.faq-item question="How does payment escrow protect both me and my customers?">
            When a buyer places an order, their payment is locked in a secure institutional escrow account. You receive verified dispatch notice. Once your dispatch partner confirms delivery or the customer accepts the order, funds are instantly settled to your Nigerian commercial bank account.
          </x-platform.faq-item>

          <x-platform.faq-item question="Can I connect my own custom domain (e.g. adebayocouture.ng)?">
            Yes. On the Merchant Pro plan, you can connect your existing <code class="font-mono bg-[var(--surface)] px-1 rounded">.ng</code>, <code class="font-mono bg-[var(--surface)] px-1 rounded">.com</code>, or <code class="font-mono bg-[var(--surface)] px-1 rounded">.com.ng</code> domain with free automatic SSL certificates.
          </x-platform.faq-item>

          <x-platform.faq-item question="What happens if a customer opens a dispute regarding an order?">
            Our automated dispute mediation timeline gives both parties 24 hours to reconcile with courier waybill tracking. If an incorrect item was dispatched, an exchange is scheduled. Escrow guarantees neither party loses funds arbitrarily.
          </x-platform.faq-item>

          <x-platform.faq-item question="Can I manage bookings and appointment deposits on WhatsApp?">
            Yes. With our Service theme, clients select literal appointment dates and time slots on your page, pay the required 30% or 50% commitment deposit, and your calendar immediately generates a pre-formatted WhatsApp appointment ticket.
          </x-platform.faq-item>

        </div>
      </div>
    </section>

    <!-- DARK CLOSING CTA -->
    <section class="section-y bg-[#090d16] text-white relative overflow-hidden">
      <div class="shell text-center max-w-3xl relative z-10">
        <p class="text-xs font-bold text-emerald-400 tracking-wider uppercase mb-3">Instant Onboarding</p>
        <h2 class="t-display-1 text-white mb-6">
          Build a storefront your customers trust.
        </h2>
        <p class="t-body-lg text-slate-300 mb-8 t-measure mx-auto">
          Join over 1,400 Nigerian merchants selling fashion, artisanal craftsmanship, beauty, and creative services without friction.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a href="#pricing" class="h-12 px-8 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-sm flex items-center justify-center hover:opacity-90 transition-opacity">
            Start Your Sovereign Storefront →
          </a>
          @if (\App\Domains\Billing\Services\Feature::enabled('discovery'))
            <a href="{{ route('discovery.index') }}" class="h-12 px-6 rounded-[var(--radius)] bg-white/10 text-white border border-white/20 font-semibold text-sm flex items-center justify-center hover:bg-white/15 transition-colors">
              Discover Verified Merchants
            </a>
          @endif
        </div>
      </div>
    </section>

@endsection