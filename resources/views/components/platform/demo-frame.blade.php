@props([
    // Two null-safe arrays describing the seeded demo storefronts:
    // ['name', 'location', 'handle', 'image', 'price', 'detail', 'href'].
    // Null when that demo is not seeded, which drops its pane rather than
    // rendering an empty browser frame.
    'service' => null,
    'store' => null,
])

<div class="lg:col-span-5 relative" x-data="{ demoTab: 'service' }">

    <!-- Real Storefront Mockup in macOS/Browser Frame -->
    <div class="rounded-[var(--radius-lg)] border border-[var(--rule)] bg-[var(--card)] shadow-[var(--shadow-lift)] overflow-hidden relative">

        <!-- Browser Header with Interactive Switcher -->
        <div class="px-4 py-2.5 bg-[var(--surface)] border-b border-[var(--rule)] flex items-center justify-between gap-3">
            <div class="flex items-center gap-1.5" aria-hidden="true">
                <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
            </div>

            <!-- URL Bar displaying active handle -->
            <div class="flex-1 text-center">
                <span class="inline-block px-3 py-0.5 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] text-[11px] font-mono text-[var(--ink-3)]"
                      x-text="demoTab === 'service'
                        ? @jsExpr($service['handle'] ?? 'mypage.ng/yourservice')
                        : @jsExpr($store['handle'] ?? 'mypage.ng/yourstore')">
                    {{ $service['handle'] ?? 'mypage.ng/yourservice' }}
                </span>
            </div>

            <!-- Live Switcher Tabs -->
            <div class="flex items-center gap-1">
                @if ($service)
                    <button type="button"
                            @click="demoTab = 'service'"
                            :class="demoTab === 'service' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'text-[var(--ink-3)] hover:text-[var(--ink)]'"
                            class="px-2 py-0.5 text-[10px] rounded transition-colors"
                            aria-label="Preview Service Demo">
                        Service
                    </button>
                @endif
                @if ($store)
                    <button type="button"
                            @click="demoTab = 'store'"
                            :class="demoTab === 'store' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'text-[var(--ink-3)] hover:text-[var(--ink)]'"
                            class="px-2 py-0.5 text-[10px] rounded transition-colors"
                            aria-label="Preview Store Demo">
                        Store
                    </button>
                @endif
            </div>
        </div>

        <!-- VIEW A: SERVICE THEME PREVIEW -->
        @if ($service)
            <div x-show="demoTab === 'service'" class="p-5 space-y-4" @if (! $store) style="display: block;" @endif>
                <div class="flex items-center justify-between pb-3 border-b border-[var(--rule)]">
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ink)]">{{ $service['name'] }}</h3>
                        <p class="t-meta text-[11px]">{{ $service['location'] }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-[var(--radius-sm)] bg-amber-500/10 text-amber-800 dark:text-amber-200 border border-amber-500/20 font-semibold text-[10px]">
                        Verified Service Atelier
                    </span>
                </div>

                <!-- Service Preview Card inside frame -->
                <div class="card p-2.5 border-[var(--rule)] bg-[var(--surface)]">
                    <div class="plate aspect-[16/9] mb-2.5">
                        @if ($service['image'])
                            <img src="{{ $service['image'] }}"
                                 alt="{{ $service['name'] }}"
                                 width="400"
                                 height="225"
                                 class="w-full h-full object-cover">
                        @endif
                        <div class="grain" aria-hidden="true"></div>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="text-xs font-bold text-[var(--ink)]">{{ $service['listing'] }}</h4>
                            <p class="t-price-sm">{{ $service['price'] }} <span class="text-[10px] text-[var(--ink-3)] font-normal">({{ $service['detail'] }})</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] text-[var(--ink-2)] pb-2 border-b border-[var(--rule)]">
                        <span class="font-bold text-[var(--brand-text)]">Next Slot:</span>
                        <span>{{ $service['meta'] }}</span>
                    </div>
                    <div class="pt-2 flex items-center justify-between">
                        <span class="text-[10px] text-[var(--ink-3)]">{{ $service['footnote'] }}</span>
                        <a href="{{ $service['href'] }}" class="px-3 py-1.5 rounded-[var(--radius-sm)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-[11px] hover:opacity-90 transition-opacity">
                            Open Full Service Demo &rarr;
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[11px] text-[var(--ink-3)] pt-2 border-t border-[var(--rule)]">
                    <span>Literal Calendar Engine</span>
                    <span class="font-bold text-[var(--brand-text)]">Secure Deposit</span>
                </div>
            </div>
        @endif

        <!-- VIEW B: STORE THEME PREVIEW -->
        @if ($store)
            <div x-show="demoTab === 'store'" class="p-5 space-y-4" style="display: none;">
                <div class="flex items-center justify-between pb-3 border-b border-[var(--rule)]">
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ink)]">{{ $store['name'] }}</h3>
                        <p class="t-meta text-[11px]">{{ $store['location'] }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] text-[var(--brand-text)] font-semibold text-[10px]">
                        Verified Merchant
                    </span>
                </div>

                <!-- Product Preview Card inside frame -->
                <div class="card p-2.5 border-[var(--rule)] bg-[var(--surface)]">
                    <div class="plate aspect-[16/9] mb-2.5">
                        @if ($store['image'])
                            <img src="{{ $store['image'] }}"
                                 alt="{{ $store['listing'] }}"
                                 width="400"
                                 height="225"
                                 class="w-full h-full object-cover">
                        @endif
                        <div class="grain" aria-hidden="true"></div>
                    </div>
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="text-xs font-bold text-[var(--ink)]">{{ $store['listing'] }}</h4>
                            <p class="t-price-sm">{{ $store['price'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-[var(--rule)]">
                        <span class="text-[10px] text-[var(--ink-3)]">{{ $store['meta'] }}</span>
                        <a href="{{ $store['href'] }}" class="px-3 py-1.5 rounded-[var(--radius-sm)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-[11px] hover:opacity-90 transition-opacity">
                            Open Full Store Demo &rarr;
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[11px] text-[var(--ink-3)] pt-2 border-t border-[var(--rule)]">
                    <span>Direct WhatsApp Bag</span>
                    <span class="font-bold text-[var(--ink)]">GIG Logistics Insured</span>
                </div>
            </div>
        @endif

    </div>

    <!-- Floating Card 1: WhatsApp Order Notification -->
    <div class="absolute -top-6 -left-6 sm:-left-10 z-20 max-w-xs p-3.5 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--brand-edge)] shadow-[var(--shadow-lift)] ring-1 ring-[var(--glow)] animate-bounce-subtle">
        <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center shrink-0">
                <x-ui.whatsapp-mark />
            </div>
            @if ($store)
                <div>
                    <p class="text-xs font-bold text-[var(--ink)]">WhatsApp Order Generated</p>
                    <p class="text-[11px] text-[var(--ink-2)] mt-0.5">
                        {{ $store['price'] }} from {{ $store['customer'] }} · <span class="font-bold text-[var(--brand-text)]">Payment Secured</span>
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Floating Card 2: Booking Confirmation -->
    @if ($service)
        <div class="absolute -bottom-6 -right-4 sm:-right-8 z-20 max-w-xs p-3.5 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] shadow-[var(--shadow-lift)]">
            <div class="flex items-start gap-2.5">
                <div class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0 border border-amber-500/30">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-[var(--ink)]">Studio Booking Confirmed</p>
                    <p class="text-[11px] text-[var(--ink-2)] mt-0.5">
                        {{ $service['listing'] }} · {{ $service['meta'] }} · <span class="font-bold text-[var(--ink)]">{{ $service['detail'] ?? $service['price'] }}</span>
                    </p>
                </div>
            </div>
        </div>
    @endif

</div>