@extends('layouts.platform')

@section('title', 'Design System &amp; Living Style Guide — mypage.ng')
@section('description', 'Every token, type step, card and button the mypage.ng platform and its storefronts are built from.')

@section('content')
<div class="pb-24" x-data="designSystemPreview">
    <!-- Hero / Architecture Overview -->
    <section class="section-y border-b border-[var(--rule)] bg-gradient-to-b from-[var(--surface)] to-[var(--paper)]">
      <div class="shell">
        <div class="max-w-3xl">
          <p class="t-meta mb-3 flex items-center gap-2">
            <span>Phase 1 Architecture</span>
            <span aria-hidden="true">·</span>
            <span>WCAG 2.1 AA Compliant</span>
            <span aria-hidden="true">·</span>
            <span>Single Token System</span>
          </p>
          <h1 class="t-display-1 text-[var(--ink)] mb-6">
            The design system for high-trust Nigerian storefronts.
          </h1>
          <p class="t-body-lg text-[var(--ink-2)] t-measure mb-8">
            One cohesive token foundation powering both the marketplace platform and bespoke tenant storefronts.
            Built with pure HTML, Tailwind utility tokens, and zero framework overhead.
          </p>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-[var(--rule)]">
            <div>
              <p class="t-meta">Typography</p>
              <p class="text-sm font-semibold text-[var(--ink)] mt-1">Jakarta + Inter</p>
            </div>
            <div>
              <p class="t-meta">Contrast Minimum</p>
              <p class="text-sm font-semibold text-[var(--ink)] mt-1">4.5:1 Body / 3:1 Lg</p>
            </div>
            <div>
              <p class="t-meta">Card Architecture</p>
              <p class="text-sm font-semibold text-[var(--ink)] mt-1">The One Card Family</p>
            </div>
            <div>
              <p class="t-meta">Commerce Currency</p>
              <p class="text-sm font-semibold text-[var(--ink)] mt-1">Nigerian Naira (₦)</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 1: Token Architecture -->
    <section id="tokens" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">01. Architecture</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Single token vocabulary</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            Every page shares the exact same CSS variable vocabulary. Surfaces step up in luminosity in dark mode,
            and brand values automatically calculate contrast-safe text and wash tints via <code class="text-xs bg-[var(--surface)] px-1.5 py-0.5 rounded border border-[var(--rule)]">color-mix()</code>.
          </p>
        </div>

        <!-- Token Swatches Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
          <!-- --brand -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--brand)] shadow-sm flex items-center justify-center text-xs font-semibold text-[var(--brand-ink)]">
              Brand Fill
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--brand</p>
              <p class="t-meta">Base accent token</p>
            </div>
          </div>

          <!-- --brand-text -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] flex items-center justify-center text-sm font-bold text-[var(--brand-text)]">
              ₦45,000 (AA)
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--brand-text</p>
              <p class="t-meta">Contrast-safe on paper</p>
            </div>
          </div>

          <!-- --brand-wash -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] border border-[var(--brand-edge)] flex items-center justify-center text-xs font-semibold text-[var(--brand-text)]">
              Wash Level 1
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--brand-wash</p>
              <p class="t-meta">7% tint / badge base</p>
            </div>
          </div>

          <!-- --brand-wash-2 -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--brand-wash-2)] border border-[var(--brand-edge)] flex items-center justify-center text-xs font-semibold text-[var(--brand-text)]">
              Wash Level 2
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--brand-wash-2</p>
              <p class="t-meta">14% hover & active tint</p>
            </div>
          </div>

          <!-- --brand-edge -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--surface)] border-2 border-[var(--brand-edge)] flex items-center justify-center text-xs font-medium text-[var(--ink)]">
              Edge Border
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--brand-edge</p>
              <p class="t-meta">Hover card border</p>
            </div>
          </div>

          <!-- --paper -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] flex items-center justify-center text-xs font-medium text-[var(--ink)]">
              Paper Layer
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--paper</p>
              <p class="t-meta">Canvas background</p>
            </div>
          </div>

          <!-- --surface -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center text-xs font-medium text-[var(--ink)]">
              Surface Layer
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--surface</p>
              <p class="t-meta">Secondary canvas</p>
            </div>
          </div>

          <!-- --card -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)] flex items-center justify-center text-xs font-medium text-[var(--ink)]">
              Card Background
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--card</p>
              <p class="t-meta">Item surface</p>
            </div>
          </div>

          <!-- --ink & --ink-2 & --ink-3 -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--paper)] border border-[var(--rule)] p-1 flex flex-col justify-around text-center">
              <span class="text-[10px] font-bold text-[var(--ink)]">Ink (15:1)</span>
              <span class="text-[10px] font-medium text-[var(--ink-2)]">Ink-2 (5.8:1)</span>
              <span class="text-[10px] text-[var(--ink-3)]">Ink-3 (4.6:1)</span>
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--ink 1, 2, 3</p>
              <p class="t-meta">Hierarchical type</p>
            </div>
          </div>

          <!-- --focus-ring -->
          <div class="p-4 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex flex-col justify-between h-36">
            <div class="w-full h-12 rounded-[var(--radius-sm)] bg-[var(--paper)] flex items-center justify-center">
              <span class="text-xs px-2 py-1 rounded bg-[var(--brand)] text-[var(--brand-ink)] outline-2 outline-[var(--focus-ring)] shadow-[0_0_0_2px_var(--paper)]">
                Focus Halo
              </span>
            </div>
            <div>
              <p class="text-xs font-mono font-bold text-[var(--ink)]">--focus-ring</p>
              <p class="t-meta">Paper inner ring halo</p>
            </div>
          </div>
        </div>

        <!-- Radii & Shadow Strip -->
        <div class="mt-8 pt-8 border-t border-[var(--rule)] grid grid-cols-1 md:grid-cols-2 gap-8">
          <div>
            <h3 class="text-sm font-bold text-[var(--ink)] mb-4">Corner Radii Discipline</h3>
            <div class="flex flex-wrap items-center gap-4">
              <div class="p-3 bg-[var(--surface)] border border-[var(--rule)] rounded-[var(--radius-sm)] text-center">
                <span class="text-xs font-mono font-bold text-[var(--ink)]">--radius-sm</span>
                <span class="block t-meta mt-1">6px (badges)</span>
              </div>
              <div class="p-3 bg-[var(--surface)] border border-[var(--rule)] rounded-[var(--radius)] text-center">
                <span class="text-xs font-mono font-bold text-[var(--ink)]">--radius</span>
                <span class="block t-meta mt-1">10px (inputs/buttons)</span>
              </div>
              <div class="p-3 bg-[var(--surface)] border border-[var(--rule)] rounded-[var(--radius-lg)] text-center">
                <span class="text-xs font-mono font-bold text-[var(--ink)]">--radius-lg</span>
                <span class="block t-meta mt-1">16px (cards/drawers)</span>
              </div>
              <div class="px-4 py-2 bg-[var(--surface)] border border-[var(--rule)] rounded-[var(--radius-pill)] text-center">
                <span class="text-xs font-mono font-bold text-[var(--ink)]">--radius-pill</span>
                <span class="block t-meta">9999px (counters)</span>
              </div>
            </div>
          </div>

          <div>
            <h3 class="text-sm font-bold text-[var(--ink)] mb-4">Elevation & Depth Restraint</h3>
            <div class="flex items-center gap-6">
              <div class="p-5 bg-[var(--card)] border border-[var(--rule)] rounded-[var(--radius-lg)] shadow-[var(--shadow-rest)]">
                <p class="text-xs font-bold text-[var(--ink)]">--shadow-rest</p>
                <p class="t-meta mt-1">Quiet 1-level hairline elevation</p>
              </div>
              <div class="p-5 bg-[var(--card)] border border-[var(--brand-edge)] rounded-[var(--radius-lg)] shadow-[var(--shadow-lift)] ring-2 ring-[var(--glow)]">
                <p class="text-xs font-bold text-[var(--ink)]">--shadow-lift</p>
                <p class="t-meta mt-1">Hover lift with ambient glow</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 2: Fluid Type Scale -->
    <section id="typography" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">02. Typographic Scale</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Fluid type without arbitrary steps</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            Strict programmatic scale using Plus Jakarta Sans for headings and tabular prices, and Inter for calm, legible prose.
            Zero italic accents inside headlines.
          </p>
        </div>

        <div class="space-y-8 bg-[var(--surface)] p-6 sm:p-10 rounded-[var(--radius-lg)] border border-[var(--rule)]">
          <!-- .t-display-1 -->
          <div class="pb-8 border-b border-[var(--rule)]">
            <span class="t-meta block mb-2 font-mono text-xs">.t-display-1 · clamp(2.5rem, 1.6rem + 4.2vw, 4.75rem) / 800 / -0.035em</span>
            <p class="t-display-1 text-[var(--ink)]">
              Lagos bridal fashion, made to measure.
            </p>
          </div>

          <!-- .t-display-2 -->
          <div class="pb-8 border-b border-[var(--rule)]">
            <span class="t-meta block mb-2 font-mono text-xs">.t-display-2 · clamp(1.75rem, 1.3rem + 2.2vw, 2.75rem) / 800 / -0.025em</span>
            <p class="t-display-2 text-[var(--ink)]">
              Quiet commerce for verified Nigerian merchants.
            </p>
          </div>

          <!-- .t-title -->
          <div class="pb-8 border-b border-[var(--rule)]">
            <span class="t-meta block mb-2 font-mono text-xs">.t-title · clamp(1.0625rem, 1rem + 0.35vw, 1.25rem) / 600</span>
            <p class="t-title text-[var(--ink)]">
              Hand-loomed Royal Aso-Oke 3-Piece Agbada with Gold Threading
            </p>
          </div>

          <!-- .t-body-lg -->
          <div class="pb-8 border-b border-[var(--rule)]">
            <span class="t-meta block mb-2 font-mono text-xs">.t-body-lg · 1.0625rem / 1.7</span>
            <p class="t-body-lg text-[var(--ink)] t-measure">
              Every storefront on mypage connects directly to WhatsApp and Nigerian instant bank transfers.
              Buyers complete transactions with zero login barriers, with payment collected by the seller's own gateway.
            </p>
          </div>

          <!-- .t-body -->
          <div class="pb-8 border-b border-[var(--rule)]">
            <span class="t-meta block mb-2 font-mono text-xs">.t-body · clamp(0.9375rem, 0.91rem + 0.15vw, 1rem) / 1.65</span>
            <p class="t-body text-[var(--ink-2)] t-measure">
              Orders placed before 2:00 PM are dispatched same-day within Lagos Island, Lekki, and Ikeja.
              Nationwide interstate deliveries are routed via insured dispatch partners with real-time tracking references.
            </p>
          </div>

          <!-- .t-meta -->
          <div class="pb-8 border-b border-[var(--rule)]">
            <span class="t-meta block mb-2 font-mono text-xs">.t-meta · 0.8125rem / 500 / --ink-3 (Never uppercase)</span>
            <p class="t-meta">
              Victoria Island, Lagos · Dispatches in 24 hours · Verified Merchant ID #MP-9841
            </p>
          </div>

          <!-- Prices: .t-price-sm & .t-price-lg -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <span class="t-meta block mb-2 font-mono text-xs">.t-price-sm · 0.9375rem / 700 / tabular-nums / --brand-text</span>
              <p class="t-price-sm">₦18,500</p>
            </div>
            <div>
              <span class="t-meta block mb-2 font-mono text-xs">.t-price-lg · clamp(1.5rem, 1.3rem + 1vw, 2rem) / 700 / tabular-nums</span>
              <p class="t-price-lg text-[var(--ink)]">₦128,500</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 3: The One Card Language -->
    <section id="cards" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">03. The One Card Language</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Unified card architecture</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            Product cards, service cards, and business directory cards share the exact same foundation:
            quiet hairline borders, image plates with subtle zoom, single stretched links, and 300ms hover transitions.
          </p>
        </div>

        <!-- 3.1 Product Cards Grid -->
        <div class="mb-16">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold text-[var(--ink)]">Product Cards (Various States)</h3>
            <span class="t-meta">1:1 Aspect Plate · Grain Layer · Accessible Stretched Link</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Product Card 1: Standard In Stock -->
            <article class="card p-3 group">
              <div class="plate aspect-square mb-3">
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/fashion-studio.svg') }}" 
                       alt="Heavyweight cotton graphic streetwear tee in washed obsidian black" 
                       width="400" 
                       height="400" 
                       loading="lazy" 
                       class="h-full w-full object-cover">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <p class="t-meta mb-1">Streetwear · Lekki</p>
                <h4 class="text-sm font-semibold text-[var(--ink)] line-clamp-1">
                  <a href="#product-modal" class="card-stretched-link hover:underline">
                    Alte Heavyweight Graphic Tee
                  </a>
                </h4>

                <div class="flex items-center gap-1.5 mt-1.5 mb-3 text-xs text-[var(--ink-3)]">
                  <span class="flex items-center text-amber-500 font-bold" aria-label="4.9 stars out of 5">
                    ★ 4.9
                  </span>
                  <span aria-hidden="true">·</span>
                  <span>48 orders</span>
                </div>

                <div class="mt-auto pt-3 border-t border-[var(--rule)] flex items-center justify-between">
                  <div>
                    <span class="sr-only">Price:</span>
                    <p class="t-price-sm">₦18,500</p>
                  </div>
                  <button type="button" 
                          @click="cartCount++"
                          class="relative z-20 px-3 py-1.5 text-xs font-semibold rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] hover:opacity-90 transition-opacity"
                          aria-label="Add Alte Heavyweight Graphic Tee to cart">
                    Add
                  </button>
                </div>
              </div>
            </article>

            <!-- Product Card 2: Discount State with Compare-at Price -->
            <article class="card p-3 group">
              <div class="plate aspect-square mb-3">
                <!-- Discount badge top-left ONLY when compare-at > price -->
                <div class="absolute top-2.5 left-2.5 z-10 px-2 py-0.5 rounded-[var(--radius-sm)] bg-[var(--ink)] text-[var(--paper)] text-[11px] font-bold tracking-tight">
                  -24%
                </div>
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/makers-table.svg') }}" 
                       alt="Hand-loomed indigenous Akwete open-front structured kimono" 
                       width="400" 
                       height="400" 
                       loading="lazy" 
                       class="h-full w-full object-cover">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <p class="t-meta mb-1">Couture · Abia State</p>
                <h4 class="text-sm font-semibold text-[var(--ink)] line-clamp-1">
                  <a href="#product-modal" class="card-stretched-link hover:underline">
                    Hand-loomed Akwete Kimono
                  </a>
                </h4>

                <div class="flex items-center gap-1.5 mt-1.5 mb-3 text-xs text-[var(--ink-3)]">
                  <span class="flex items-center text-amber-500 font-bold" aria-label="5.0 stars out of 5">
                    ★ 5.0
                  </span>
                  <span aria-hidden="true">·</span>
                  <span>Verified craft</span>
                </div>

                <div class="mt-auto pt-3 border-t border-[var(--rule)] flex items-center justify-between">
                  <div class="flex items-baseline gap-2">
                    <p class="t-price-sm">₦65,000</p>
                    <del class="text-xs text-[var(--ink-3)] tabular-nums">₦85,000</del>
                  </div>
                  <button type="button" 
                          @click="cartCount++"
                          class="relative z-20 px-3 py-1.5 text-xs font-semibold rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] hover:opacity-90 transition-opacity"
                          aria-label="Add Hand-loomed Akwete Kimono to cart">
                    Add
                  </button>
                </div>
              </div>
            </article>

            <!-- Product Card 3: Out of Stock State (Word-labeled, not color alone) -->
            <article class="card p-3 group opacity-85">
              <div class="plate aspect-square mb-3">
                <div class="absolute inset-0 bg-[var(--paper)]/50 z-10 flex items-center justify-center">
                  <span class="px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] text-[var(--ink)] text-xs font-bold border border-[var(--rule)] shadow-sm">
                    Out of stock
                  </span>
                </div>
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/fashion-studio.svg') }}" 
                       alt="Tailored Nigerian wedding agbada embroidery in emerald silk" 
                       width="400" 
                       height="400" 
                       loading="lazy" 
                       class="h-full w-full object-cover grayscale">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <p class="t-meta mb-1">Bespoke · Surulere</p>
                <h4 class="text-sm font-semibold text-[var(--ink)] line-clamp-1">
                  <a href="#product-modal" class="card-stretched-link hover:underline">
                    Embroidered Agbada 3-Piece
                  </a>
                </h4>

                <div class="flex items-center gap-1.5 mt-1.5 mb-3 text-xs text-[var(--ink-3)]">
                  <span>Next batch in 2 weeks</span>
                </div>

                <div class="mt-auto pt-3 border-t border-[var(--rule)] flex items-center justify-between">
                  <p class="t-price-sm text-[var(--ink-3)]">₦110,000</p>
                  <button type="button" 
                          disabled 
                          class="relative z-20 px-3 py-1.5 text-xs font-medium rounded-[var(--radius)] bg-[var(--surface)] text-[var(--ink-3)] border border-[var(--rule)] cursor-not-allowed"
                          aria-label="Embroidered Agbada 3-Piece is out of stock">
                    Sold Out
                  </button>
                </div>
              </div>
            </article>

            <!-- Product Card 4: Added In Cart State -->
            <article class="card p-3 group border-[var(--brand-edge)] ring-1 ring-[var(--brand-edge)]">
              <div class="plate aspect-square mb-3">
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/food-home.svg') }}" 
                       alt="Top-grain Nigerian leather minimal daily tote bag in dark chestnut" 
                       width="400" 
                       height="400" 
                       loading="lazy" 
                       class="h-full w-full object-cover">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <p class="t-meta mb-1">Leather · Kano Tannery</p>
                <h4 class="text-sm font-semibold text-[var(--ink)] line-clamp-1">
                  <a href="#product-modal" class="card-stretched-link hover:underline">
                    Minimalist Calfskin Daily Tote
                  </a>
                </h4>

                <div class="flex items-center gap-1.5 mt-1.5 mb-3 text-xs text-[var(--ink-3)]">
                  <span class="flex items-center text-amber-500 font-bold">★ 4.95</span>
                  <span aria-hidden="true">·</span>
                  <span>Hand-stitched</span>
                </div>

                <div class="mt-auto pt-3 border-t border-[var(--rule)] flex items-center justify-between">
                  <p class="t-price-sm">₦52,000</p>
                  <span class="relative z-20 inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-[var(--radius)] bg-[var(--brand-wash-2)] text-[var(--brand-text)] border border-[var(--brand-edge)]" aria-live="polite">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    In Cart
                  </span>
                </div>
              </div>
            </article>

          </div>
        </div>

        <!-- 3.2 Service Cards Grid -->
        <div class="mb-16">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold text-[var(--ink)]">Service Cards (Bookable Offerings)</h3>
            <span class="t-meta">4:3 Plate · Turnaround & Deposit Rows · Deliverables List</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Service Card 1: Standard Service -->
            <article class="card p-4 group">
              <div class="plate aspect-[4/3] mb-4">
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/beauty-studio.svg') }}" 
                       alt="Professional bridal makeup and gele tying studio session in Lagos" 
                       width="600" 
                       height="450" 
                       loading="lazy" 
                       class="h-full w-full object-cover">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <p class="t-meta">Beauty & Bridal · Studio</p>
                  <span class="text-xs text-[var(--ink-3)] font-medium">Lekki Studio</span>
                </div>

                <h4 class="t-title text-[var(--ink)] mb-2">
                  <a href="#service-booking" class="card-stretched-link hover:underline">
                    Bridal Gele Styling & Makeup Artistry
                  </a>
                </h4>

                <p class="text-xs text-[var(--ink-2)] line-clamp-3 mb-4 leading-relaxed">
                  Full luxury traditional engagement styling. Includes premium skin prep, long-wear HD beauty finish, custom autogele shaping, and touchup kit.
                </p>

                <!-- 4 Deliverables with +N more -->
                <ul class="text-xs text-[var(--ink-2)] space-y-1 mb-4">
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>1 on 1 Consultation & Face mapping</span>
                  </li>
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>HD 16-Hour Waterproof Glam</span>
                  </li>
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>Custom infinity or pleat Gele</span>
                  </li>
                  <li class="text-[var(--ink-3)] font-medium pl-2.5">+2 more deliverables included</li>
                </ul>

                <!-- Metadata Row -->
                <div class="grid grid-cols-2 gap-2 text-xs py-2.5 border-y border-[var(--rule)] mb-4">
                  <div>
                    <span class="text-[var(--ink-3)] block">Duration</span>
                    <span class="font-semibold text-[var(--ink)]">2.5 hours</span>
                  </div>
                  <div>
                    <span class="text-[var(--ink-3)] block">Deposit Required</span>
                    <span class="font-semibold text-[var(--ink)]">₦15,000 (33%)</span>
                  </div>
                </div>

                <!-- Price Stack aligned at bottom -->
                <div class="mt-auto flex items-center justify-between pt-2">
                  <div>
                    <span class="sr-only">Service fee:</span>
                    <p class="t-price-sm">₦45,000 <span class="text-xs font-normal text-[var(--ink-3)]">/ session</span></p>
                  </div>
                  <span class="text-xs font-semibold text-[var(--brand-text)] group-hover:underline">
                    View & book →
                  </span>
                </div>
              </div>
            </article>

            <!-- Service Card 2: Featured Service with Ribbon Sitting ON Top Border -->
            <article class="card p-4 group relative pt-6">
              <!-- Featured Ribbon resting ON the border hairline -->
              <div class="absolute -top-3 left-6 z-20 px-3 py-0.5 rounded-[var(--radius-pill)] bg-[var(--brand)] text-[var(--brand-ink)] text-[11px] font-bold tracking-tight shadow-sm">
                Most Requested
              </div>

              <div class="plate aspect-[4/3] mb-4">
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/food-home.svg') }}" 
                       alt="Commercial fashion studio photo setup with medium format camera" 
                       width="600" 
                       height="450" 
                       loading="lazy" 
                       class="h-full w-full object-cover">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <p class="t-meta">Photography · Commercial</p>
                  <span class="text-xs text-[var(--ink-3)] font-medium">Victoria Island</span>
                </div>

                <h4 class="t-title text-[var(--ink)] mb-2">
                  <a href="#service-booking" class="card-stretched-link hover:underline">
                    Editorial Lookbook & Campaign Production
                  </a>
                </h4>

                <p class="text-xs text-[var(--ink-2)] line-clamp-3 mb-4 leading-relaxed">
                  Complete commercial campaign shoot for fashion and apparel brands. Full studio lighting, creative direction, tethered capture, and high-res retouching.
                </p>

                <ul class="text-xs text-[var(--ink-2)] space-y-1 mb-4">
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>Up to 12 Styled Garments</span>
                  </li>
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>24 Retouched Commercial Deliverables</span>
                  </li>
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>Commercial Web & Billboard License</span>
                  </li>
                  <li class="text-[var(--ink-3)] font-medium pl-2.5">+3 raw proof galleries</li>
                </ul>

                <div class="grid grid-cols-2 gap-2 text-xs py-2.5 border-y border-[var(--rule)] mb-4">
                  <div>
                    <span class="text-[var(--ink-3)] block">Turnaround</span>
                    <span class="font-semibold text-[var(--ink)]">4 business days</span>
                  </div>
                  <div>
                    <span class="text-[var(--ink-3)] block">Capacity</span>
                    <span class="font-semibold text-[var(--ink)]">Max 2 per week</span>
                  </div>
                </div>

                <div class="mt-auto flex items-center justify-between pt-2">
                  <div>
                    <p class="t-price-sm">₦150,000 <span class="text-xs font-normal text-[var(--ink-3)]">/ half-day</span></p>
                  </div>
                  <span class="text-xs font-semibold text-[var(--brand-text)] group-hover:underline">
                    View & book →
                  </span>
                </div>
              </div>
            </article>

            <!-- Service Card 3: Fully Booked Service -->
            <article class="card p-4 group opacity-85">
              <div class="plate aspect-[4/3] mb-4">
                <div class="absolute inset-0 bg-[var(--paper)]/50 z-10 flex items-center justify-center">
                  <span class="px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--surface)] text-[var(--ink)] text-xs font-bold border border-[var(--rule)] shadow-sm">
                    Booked for October
                  </span>
                </div>
                <div class="plate-media h-full">
                  <img src="{{ asset('images/landing/makers-table.svg') }}" 
                       alt="Traditional Nigerian wedding ceremony with documentary photography" 
                       width="600" 
                       height="450" 
                       loading="lazy" 
                       class="h-full w-full object-cover">
                </div>
                <div class="grain" aria-hidden="true"></div>
              </div>

              <div class="flex flex-col flex-1">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <p class="t-meta">Video & Film · Events</p>
                  <span class="text-xs text-[var(--ink-3)] font-medium">Abuja & Lagos</span>
                </div>

                <h4 class="t-title text-[var(--ink)] mb-2">
                  <a href="#service-booking" class="card-stretched-link hover:underline">
                    Traditional Wedding 4K Documentary Film
                  </a>
                </h4>

                <p class="text-xs text-[var(--ink-2)] line-clamp-3 mb-4 leading-relaxed">
                  Cinematic two-camera wedding storytelling. Full ceremony documentation, drone aerials of venue, audio master of vows and family blessings.
                </p>

                <ul class="text-xs text-[var(--ink-2)] space-y-1 mb-4">
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>10-Minute Highlight Teaser</span>
                  </li>
                  <li class="flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    <span>60-Minute Feature Documentary</span>
                  </li>
                  <li class="text-[var(--ink-3)] font-medium pl-2.5">+Drone capture included</li>
                </ul>

                <div class="grid grid-cols-2 gap-2 text-xs py-2.5 border-y border-[var(--rule)] mb-4">
                  <div>
                    <span class="text-[var(--ink-3)] block">Turnaround</span>
                    <span class="font-semibold text-[var(--ink)]">14 days</span>
                  </div>
                  <div>
                    <span class="text-[var(--ink-3)] block">Deposit Required</span>
                    <span class="font-semibold text-[var(--ink)]">50% upfront</span>
                  </div>
                </div>

                <div class="mt-auto flex items-center justify-between pt-2">
                  <div>
                    <p class="t-price-sm text-[var(--ink-3)]">₦350,000 <span class="text-xs font-normal">/ day</span></p>
                  </div>
                  <span class="text-xs font-semibold text-[var(--ink-3)]">
                    Waitlist only
                  </span>
                </div>
              </div>
            </article>

          </div>
        </div>

        <!-- 3.3 Business Cards (Opaque vs Translucent Glass Variant) -->
        <div>
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold text-[var(--ink)]">Business Directory Cards</h3>
            <span class="t-meta">Opaque Everywhere · Glassmorphic Variant ONLY on Hero Wash</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Standard Opaque Business Card -->
            <article class="card p-5 group">
              <div class="flex items-start gap-4">
                <div class="w-16 h-16 rounded-[var(--radius)] overflow-hidden shrink-0 border border-[var(--rule)] bg-[var(--surface)]">
                  <img src="{{ asset('images/landing/beauty-studio.svg') }}" 
                       alt="Founder avatar for Adeola Couture Lagos" 
                       width="64" 
                       height="64" 
                       loading="lazy" 
                       class="w-full h-full object-cover">
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[var(--brand-text)] bg-[var(--brand-wash)] px-2 py-0.5 rounded-[var(--radius-sm)] border border-[var(--brand-edge)]">
                      <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                      Verified Store
                    </span>
                    <span class="text-xs font-medium text-[var(--ink-3)]">₦₦ · Mid Tier</span>
                  </div>

                  <h4 class="text-base font-bold text-[var(--ink)] mt-1.5 truncate">
                    <a href="#store-preview" class="card-stretched-link hover:underline">
                      Adeola Luxury Bridal & Aso-Oke
                    </a>
                  </h4>

                  <p class="t-meta mt-0.5">Victoria Island, Lagos · 4.9 ★ (124 reviews)</p>
                </div>
              </div>

              <p class="text-xs text-[var(--ink-2)] mt-4 line-clamp-2 leading-relaxed">
                Specializing in bespoke Yoruba wedding agbada, auto-gele sets, and custom bridal beadwork. Direct WhatsApp fulfilment with secure online payment.
              </p>

              <div class="mt-4 pt-3 border-t border-[var(--rule)] flex items-center justify-between text-xs">
                <span class="text-[var(--ink-3)] flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                  Replies in ~10 mins on WhatsApp
                </span>
                <span class="font-semibold text-[var(--brand-text)] group-hover:underline">Visit Storefront →</span>
              </div>
            </article>

            <!-- Translucent Hero Variant (card-glass over radial wash) -->
            <div class="p-6 rounded-[var(--radius-lg)] bg-gradient-to-br from-[var(--brand-wash-2)] via-[var(--surface)] to-[var(--brand-wash)] border border-[var(--brand-edge)] relative overflow-hidden">
              <span class="t-meta text-xs font-mono mb-3 block text-[var(--brand-text)]">Demonstration: Translucent card over hero wash</span>

              <article class="card card-glass p-5 group border border-white/40 dark:border-white/10 shadow-lg">
                <div class="flex items-start gap-4">
                  <div class="w-16 h-16 rounded-[var(--radius)] overflow-hidden shrink-0 border border-[var(--rule)] bg-[var(--surface)]">
                    <img src="{{ asset('images/landing/beauty-studio.svg') }}" 
                         alt="Founder avatar for Kano Tannery Craftsmen" 
                         width="64" 
                         height="64" 
                         loading="lazy" 
                         class="w-full h-full object-cover">
                  </div>

                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                      <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[var(--brand-text)] bg-[var(--brand-wash)] px-2 py-0.5 rounded-[var(--radius-sm)] border border-[var(--brand-edge)]">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        Artisanal Guild
                      </span>
                      <span class="text-xs font-medium text-[var(--ink-3)]">₦₦₦ · Premium</span>
                    </div>

                    <h4 class="text-base font-bold text-[var(--ink)] mt-1.5 truncate">
                      <a href="#store-preview" class="card-stretched-link hover:underline">
                        Kano Tannery Leather Guild
                      </a>
                    </h4>

                    <p class="t-meta mt-0.5">Kano & Abuja · 5.0 ★ (89 orders)</p>
                  </div>
                </div>

                <p class="text-xs text-[var(--ink-2)] mt-4 line-clamp-2 leading-relaxed">
                  Heritage vegetable-tanned leather goods from northern Nigeria. Laptop folios, weekend duffels, and custom brass-buckled belts.
                </p>

                <div class="mt-4 pt-3 border-t border-[var(--rule)] flex items-center justify-between text-xs">
                  <span class="text-[var(--ink-3)]">Ships nationwide in 48h</span>
                  <span class="font-semibold text-[var(--brand-text)] group-hover:underline">Explore Catalogue →</span>
                </div>
              </article>
            </div>

          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 4: Interactive Controls & Forms -->
    <section id="controls" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">04. Interactive Form Architecture</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Every control has a visible label</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            No placeholder-only inputs. 44px minimum touch targets, visible focus halo, two separate number inputs for price range (no clumsy dual slider),
            and accessible variant buttons.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          
          <!-- Column 1: Input Fields & Steppers -->
          <div class="space-y-6 bg-[var(--surface)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)]">
            <h3 class="text-sm font-bold text-[var(--ink)] border-b border-[var(--rule)] pb-3">Field Controls & Validation</h3>

            <!-- Standard Input with Visible Label -->
            <div>
              <label for="sample-full-name" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                Full Name <span class="text-red-500" aria-hidden="true">*</span>
              </label>
              <input type="text" 
                     id="sample-full-name" 
                     name="fullName"
                     value="Chinedu Okonkwo"
                     placeholder="e.g. Babatunde Adeleke"
                     class="w-full h-11 px-3.5 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] transition-colors">
              <p class="t-meta mt-1.5">Used for delivery dispatch and order updates.</p>
            </div>

            <!-- Phone Number with +234 Nigerian Prefix -->
            <div>
              <label for="sample-phone" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                WhatsApp Phone Number <span class="text-red-500" aria-hidden="true">*</span>
              </label>
              <div class="relative flex items-center">
                <span class="absolute left-3 text-xs font-bold text-[var(--ink-3)] select-none">
                  +234
                </span>
                <input type="tel" 
                       id="sample-phone" 
                       name="phone"
                       value="803 456 7890"
                       class="w-full h-11 pl-14 pr-3.5 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] font-mono tabular-nums">
              </div>
              <p class="t-meta mt-1.5">Instant order confirmation receipt will be sent here.</p>
            </div>

            <!-- Price Min & Max (Two Number Inputs, Strict No Dual Slider) -->
            <div>
              <fieldset>
                <legend class="text-xs font-bold text-[var(--ink)] mb-1.5">Price Range Filter (₦)</legend>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label for="price-min" class="t-meta block mb-1">Minimum (₦)</label>
                    <input type="number" 
                           id="price-min" 
                           name="priceMin"
                           value="10000"
                           step="1000"
                           min="0"
                           class="w-full h-11 px-3.5 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] font-mono tabular-nums">
                  </div>
                  <div>
                    <label for="price-max" class="t-meta block mb-1">Maximum (₦)</label>
                    <input type="number" 
                           id="price-max" 
                           name="priceMax"
                           value="75000"
                           step="1000"
                           min="0"
                           class="w-full h-11 px-3.5 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)] font-mono tabular-nums">
                  </div>
                </div>
              </fieldset>
            </div>

            <!-- 48px Quantity Stepper -->
            <div>
              <label for="sample-qty" class="block text-xs font-bold text-[var(--ink)] mb-1.5">
                Order Quantity
              </label>
              <div class="inline-flex items-center rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] overflow-hidden">
                <button type="button" 
                        @click="if (qty > 1) qty--" 
                        class="w-12 h-12 flex items-center justify-center text-[var(--ink)] hover:bg-[var(--surface)] transition-colors text-base font-bold disabled:opacity-40"
                        :disabled="qty <= 1"
                        aria-label="Decrease quantity">
                  −
                </button>
                <input type="text" 
                       id="sample-qty" 
                       readonly 
                       :value="qty" 
                       class="w-12 h-12 text-center text-sm font-bold text-[var(--ink)] bg-transparent border-x border-[var(--rule)] tabular-nums"
                       aria-live="polite">
                <button type="button" 
                        @click="qty++" 
                        class="w-12 h-12 flex items-center justify-center text-[var(--ink)] hover:bg-[var(--surface)] transition-colors text-base font-bold"
                        aria-label="Increase quantity">
                  +
                </button>
              </div>
            </div>

          </div>

          <!-- Column 2: Search Row, Option Pills & Radio Cards -->
          <div class="space-y-6 bg-[var(--surface)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)]">
            <h3 class="text-sm font-bold text-[var(--ink)] border-b border-[var(--rule)] pb-3">Search Row, Variants & Selection Cards</h3>

            <!-- Search Row with Clear Button & City Select -->
            <form action="#search" method="GET" class="space-y-3">
              <label for="sample-search" class="block text-xs font-bold text-[var(--ink)]">
                Directory Search
              </label>
              <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                  <input type="text" 
                         id="sample-search" 
                         name="q"
                         x-model="searchQuery"
                         placeholder="Search fabrics, bakers, studios..." 
                         class="w-full h-11 pl-9 pr-9 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                  <svg class="w-4 h-4 text-[var(--ink-3)] absolute left-3 top-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                  <button type="button" 
                          x-show="searchQuery.length > 0" 
                          @click="searchQuery = ''"
                          class="absolute right-3 top-3 text-[var(--ink-3)] hover:text-[var(--ink)]"
                          aria-label="Clear search text">
                    ✕
                  </button>
                </div>

                <div class="sm:w-44">
                  <label for="city-select" class="sr-only">Select Nigerian City</label>
                  <select id="city-select" 
                          name="city"
                          x-model="selectedCity"
                          class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] focus:border-[var(--brand)]">
                    <option value="">All Cities</option>
                    <option value="lagos">Lagos (All Zones)</option>
                    <option value="abuja">Abuja (FCT)</option>
                    <option value="ibadan">Ibadan</option>
                    <option value="ph">Port Harcourt</option>
                    <option value="kano">Kano</option>
                  </select>
                </div>

                <button type="submit" 
                        class="h-11 px-4 text-xs font-bold rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] hover:opacity-90 transition-opacity whitespace-nowrap">
                  Search
                </button>
              </div>
            </form>

            <!-- Removable Filter Chips with SR text -->
            <div>
              <span class="block text-xs font-bold text-[var(--ink)] mb-2">Active Filter Chips</span>
              <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--paper)] text-xs text-[var(--ink)] border border-[var(--rule)]">
                  <span>City: Lagos</span>
                  <button type="button" class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1" aria-label="Remove Lagos city filter">×</button>
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[var(--radius-sm)] bg-[var(--paper)] text-xs text-[var(--ink)] border border-[var(--rule)]">
                  <span>Price: Under ₦50,000</span>
                  <button type="button" class="text-[var(--ink-3)] hover:text-[var(--ink)] font-bold ml-1" aria-label="Remove price filter">×</button>
                </span>
                <button type="button" class="text-xs font-semibold text-[var(--brand-text)] hover:underline ml-2">
                  Clear all filters
                </button>
              </div>
            </div>

            <!-- Variant Option Selectors (Size & Color) with aria-pressed -->
            <div class="space-y-3 pt-2">
              <fieldset>
                <legend class="text-xs font-bold text-[var(--ink)] mb-2">Select Garment Size</legend>
                <div class="flex flex-wrap gap-2" role="group" aria-label="Garment sizes">
                  <button type="button" 
                          @click="activeVariant = 'S'"
                          :aria-pressed="activeVariant === 'S'"
                          :class="activeVariant === 'S' ? 'bg-[var(--brand)] text-[var(--brand-ink)] border-[var(--brand)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                          class="w-11 h-11 rounded-[var(--radius)] border text-xs flex items-center justify-center transition-colors">
                    S
                  </button>
                  <button type="button" 
                          @click="activeVariant = 'M'"
                          :aria-pressed="activeVariant === 'M'"
                          :class="activeVariant === 'M' ? 'bg-[var(--brand)] text-[var(--brand-ink)] border-[var(--brand)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                          class="w-11 h-11 rounded-[var(--radius)] border text-xs flex items-center justify-center transition-colors">
                    M
                  </button>
                  <button type="button" 
                          @click="activeVariant = 'L'"
                          :aria-pressed="activeVariant === 'L'"
                          :class="activeVariant === 'L' ? 'bg-[var(--brand)] text-[var(--brand-ink)] border-[var(--brand)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                          class="w-11 h-11 rounded-[var(--radius)] border text-xs flex items-center justify-center transition-colors">
                    L
                  </button>
                  <button type="button" 
                          @click="activeVariant = 'XL'"
                          :aria-pressed="activeVariant === 'XL'"
                          :class="activeVariant === 'XL' ? 'bg-[var(--brand)] text-[var(--brand-ink)] border-[var(--brand)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                          class="w-11 h-11 rounded-[var(--radius)] border text-xs flex items-center justify-center transition-colors">
                    XL
                  </button>
                </div>
              </fieldset>
            </div>

            <!-- Radio Payment Selection Cards -->
            <div>
              <fieldset>
                <legend class="text-xs font-bold text-[var(--ink)] mb-2">Payment Structure</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label :class="paymentType === 'deposit' ? 'border-[var(--brand)] ring-1 ring-[var(--brand)] bg-[var(--brand-wash)]' : 'border-[var(--rule)] bg-[var(--paper)]'"
                         class="flex items-start gap-3 p-3.5 rounded-[var(--radius)] border cursor-pointer transition-all">
                    <input type="radio" 
                           name="paymentStructure" 
                           value="deposit"
                           x-model="paymentType"
                           class="mt-1 text-[var(--brand)] focus:ring-[var(--focus-ring)]">
                    <div>
                      <span class="block text-xs font-bold text-[var(--ink)]">30% Deposit</span>
                      <span class="t-meta mt-0.5">Pay ₦13,500 now. Balance released after dispatch confirmation.</span>
                    </div>
                  </label>

                  <label :class="paymentType === 'full' ? 'border-[var(--brand)] ring-1 ring-[var(--brand)] bg-[var(--brand-wash)]' : 'border-[var(--rule)] bg-[var(--paper)]'"
                         class="flex items-start gap-3 p-3.5 rounded-[var(--radius)] border cursor-pointer transition-all">
                    <input type="radio" 
                           name="paymentStructure" 
                           value="full"
                           x-model="paymentType"
                           class="mt-1 text-[var(--brand)] focus:ring-[var(--focus-ring)]">
                    <div>
                      <span class="block text-xs font-bold text-[var(--ink)]">Full Payment</span>
                      <span class="t-meta mt-0.5">Pay ₦45,000 at booking. Instant dispatch priority.</span>
                    </div>
                  </label>
                </div>
              </fieldset>
            </div>

          </div>

        </div>
      </div>
    </section>

    <!-- SECTION 5: In-Page Booking Calendar Component -->
    <section id="booking" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">05. Service Booking Module</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Literal HTML booking calendar</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            Every date is a literal, pre-rendered button in the HTML file (no JS array loops or client-side calendar generation).
            Available vs unavailable vs selected states are distinguishable by shape, strikethrough, and text — never color alone.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          <!-- Calendar Matrix (8 Columns) -->
          <div class="lg:col-span-8 bg-[var(--surface)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)]">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-[var(--rule)]">
              <div>
                <h3 class="text-base font-bold text-[var(--ink)]">October 2026</h3>
                <p class="t-meta">Lekki Studio Appointments</p>
              </div>
              <div class="flex items-center gap-2">
                <button type="button" class="p-2 rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink)] hover:bg-[var(--paper)]" aria-label="Previous month">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" class="p-2 rounded-[var(--radius-sm)] border border-[var(--rule)] text-[var(--ink)] hover:bg-[var(--paper)]" aria-label="Next month">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </button>
              </div>
            </div>

            <!-- Days of Week Header -->
            <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-bold text-[var(--ink-3)]">
              <span>Mon</span>
              <span>Tue</span>
              <span>Wed</span>
              <span>Thu</span>
              <span>Fri</span>
              <span>Sat</span>
              <span>Sun</span>
            </div>

            <!-- Literal HTML Days Grid -->
            <div class="grid grid-cols-7 gap-2" role="grid" aria-label="October 2026 booking dates">
              <!-- Empty buffer cells -->
              <div class="h-12 rounded-[var(--radius)] opacity-20"></div>
              <div class="h-12 rounded-[var(--radius)] opacity-20"></div>
              
              <!-- 1 to 31 as literal HTML buttons with state attributes -->
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 1, unavailable">1</button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 2, unavailable">2</button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 3, unavailable">3</button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 4, unavailable">4</button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 5, unavailable">5</button>

              <button type="button" @click="selectedBookingDate = '2026-10-06'" :class="selectedBookingDate === '2026-10-06' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors" aria-label="October 6, 2 slots available">
                <span>6</span>
                <span class="text-[9px] font-normal opacity-80">2 left</span>
              </button>
              <button type="button" @click="selectedBookingDate = '2026-10-07'" :class="selectedBookingDate === '2026-10-07' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors" aria-label="October 7, 3 slots available">
                <span>7</span>
                <span class="text-[9px] font-normal opacity-80">3 left</span>
              </button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 8, fully booked">8</button>
              <button type="button" @click="selectedBookingDate = '2026-10-09'" :class="selectedBookingDate === '2026-10-09' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors" aria-label="October 9, 1 slot available">
                <span>9</span>
                <span class="text-[9px] font-normal opacity-80">1 left</span>
              </button>
              <button type="button" @click="selectedBookingDate = '2026-10-10'" :class="selectedBookingDate === '2026-10-10' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors" aria-label="October 10, weekend studio">
                <span>10</span>
                <span class="text-[9px] font-normal opacity-80">Weekend</span>
              </button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true" aria-label="October 11, studio closed">11</button>
              <button type="button" @click="selectedBookingDate = '2026-10-12'" :class="selectedBookingDate === '2026-10-12' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">12</button>

              <button type="button" @click="selectedBookingDate = '2026-10-13'" :class="selectedBookingDate === '2026-10-13' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">13</button>
              <button type="button" @click="selectedBookingDate = '2026-10-14'" :class="selectedBookingDate === '2026-10-14' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">14</button>
              <button type="button" @click="selectedBookingDate = '2026-10-15'" :class="selectedBookingDate === '2026-10-15' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">15</button>
              <button type="button" @click="selectedBookingDate = '2026-10-16'" :class="selectedBookingDate === '2026-10-16' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">16</button>
              <button type="button" @click="selectedBookingDate = '2026-10-17'" :class="selectedBookingDate === '2026-10-17' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">17</button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true">18</button>
              <button type="button" @click="selectedBookingDate = '2026-10-19'" :class="selectedBookingDate === '2026-10-19' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">19</button>

              <button type="button" @click="selectedBookingDate = '2026-10-20'" :class="selectedBookingDate === '2026-10-20' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">20</button>
              <button type="button" @click="selectedBookingDate = '2026-10-21'" :class="selectedBookingDate === '2026-10-21' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">21</button>
              <button type="button" @click="selectedBookingDate = '2026-10-22'" :class="selectedBookingDate === '2026-10-22' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold ring-2 ring-[var(--focus-ring)]' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors" aria-selected="true" aria-label="October 22, selected date">
                <span>22</span>
                <span class="text-[9px] font-bold">Selected</span>
              </button>
              <button type="button" @click="selectedBookingDate = '2026-10-23'" :class="selectedBookingDate === '2026-10-23' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">23</button>
              <button type="button" @click="selectedBookingDate = '2026-10-24'" :class="selectedBookingDate === '2026-10-24' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">24</button>
              <button type="button" disabled class="h-12 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-[var(--ink-3)] text-xs flex flex-col items-center justify-center opacity-40 cursor-not-allowed line-through" aria-disabled="true">25</button>
              <button type="button" @click="selectedBookingDate = '2026-10-26'" :class="selectedBookingDate === '2026-10-26' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">26</button>

              <button type="button" @click="selectedBookingDate = '2026-10-27'" :class="selectedBookingDate === '2026-10-27' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">27</button>
              <button type="button" @click="selectedBookingDate = '2026-10-28'" :class="selectedBookingDate === '2026-10-28' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">28</button>
              <button type="button" @click="selectedBookingDate = '2026-10-29'" :class="selectedBookingDate === '2026-10-29' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">29</button>
              <button type="button" @click="selectedBookingDate = '2026-10-30'" :class="selectedBookingDate === '2026-10-30' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">30</button>
              <button type="button" @click="selectedBookingDate = '2026-10-31'" :class="selectedBookingDate === '2026-10-31' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] hover:border-[var(--brand-edge)]'" class="h-12 rounded-[var(--radius)] border border-[var(--rule)] text-xs flex flex-col items-center justify-center transition-colors">31</button>
            </div>

            <!-- Time Slot Chips -->
            <div class="mt-8 pt-6 border-t border-[var(--rule)]">
              <span class="block text-xs font-bold text-[var(--ink)] mb-3">Available Appointment Slots for October 22</span>
              <div class="flex flex-wrap gap-2.5">
                <button type="button" 
                        @click="selectedSlot = '09:00 AM'"
                        :class="selectedSlot === '09:00 AM' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                        class="px-4 py-2.5 rounded-[var(--radius)] text-xs font-medium tabular-nums transition-colors"
                        aria-pressed="selectedSlot === '09:00 AM'">
                  09:00 AM
                </button>
                <button type="button" 
                        @click="selectedSlot = '11:30 AM'"
                        :class="selectedSlot === '11:30 AM' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                        class="px-4 py-2.5 rounded-[var(--radius)] text-xs font-medium tabular-nums transition-colors"
                        aria-pressed="selectedSlot === '11:30 AM'">
                  11:30 AM
                </button>
                <button type="button" 
                        disabled 
                        class="px-4 py-2.5 rounded-[var(--radius)] text-xs font-medium tabular-nums bg-[var(--paper)] text-[var(--ink-3)] border border-[var(--rule)] opacity-40 cursor-not-allowed line-through"
                        aria-disabled="true">
                  02:00 PM (Booked)
                </button>
                <button type="button" 
                        @click="selectedSlot = '04:30 PM'"
                        :class="selectedSlot === '04:30 PM' ? 'bg-[var(--brand)] text-[var(--brand-ink)] font-bold' : 'bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)]'"
                        class="px-4 py-2.5 rounded-[var(--radius)] text-xs font-medium tabular-nums transition-colors"
                        aria-pressed="selectedSlot === '04:30 PM'">
                  04:30 PM
                </button>
              </div>
            </div>
          </div>

          <!-- Sticky Summary Panel (4 Columns) -->
          <div class="lg:col-span-4 sticky top-24 bg-[var(--card)] p-6 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
            <h3 class="text-sm font-bold text-[var(--ink)] pb-3 border-b border-[var(--rule)]">Booking Summary</h3>

            <div class="space-y-3.5 my-4 text-xs">
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-3)]">Service</span>
                <span class="font-semibold text-[var(--ink)]">Bridal Gele & Makeup</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-3)]">Selected Date</span>
                <span class="font-semibold text-[var(--ink)]" x-text="selectedBookingDate">2026-10-22</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-3)]">Time Slot</span>
                <span class="font-semibold text-[var(--ink)]" x-text="selectedSlot">11:30 AM</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-3)]">Studio Location</span>
                <span class="font-semibold text-[var(--ink)]">Lekki Phase 1, Lagos</span>
              </div>
              <div class="flex items-center justify-between pt-3 border-t border-[var(--rule)]">
                <span class="font-semibold text-[var(--ink)]">Deposit Due Now</span>
                <span class="t-price-sm">₦15,000</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-3)]">Remaining Balance</span>
                <span class="text-xs font-semibold text-[var(--ink-2)] tabular-nums">₦30,000 at studio</span>
              </div>
            </div>

            <button type="button" 
                    class="w-full h-12 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              <span>Confirm Appointment via WhatsApp</span>
            </button>

            <p class="t-meta text-center mt-3">
              Protected by mypage verified merchant guarantee.
            </p>
          </div>

        </div>
      </div>
    </section>

    <!-- SECTION 6: Cart Receipt & Payment States -->
    <section id="receipt" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">06. Order Receipt & Checkout Engine</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Tabular receipts & payment transparency</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            Nigerian buyers demand certainty. Clean tabular breakdowns with zero hidden kobo fees, explicit delivery dispatch estimates,
            and a clear 3-state payment status.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          <!-- Receipt Style Breakdown (7 Columns) -->
          <div class="lg:col-span-7 bg-[var(--card)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
            <div class="flex items-center justify-between pb-4 border-b border-[var(--rule)]">
              <div>
                <span class="text-xs font-mono font-bold text-[var(--ink-3)]">ORDER #MP-LAG-9842</span>
                <h3 class="text-base font-bold text-[var(--ink)] mt-0.5">Order Receipt</h3>
              </div>
              <span class="px-2.5 py-1 rounded-[var(--radius-sm)] bg-[var(--brand-wash)] text-[var(--brand-text)] border border-[var(--brand-edge)] text-xs font-bold">
                Secure Payment
              </span>
            </div>

            <!-- Itemized List -->
            <div class="divide-y divide-[var(--rule)] my-4">
              <div class="py-3.5 flex items-center justify-between text-xs">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-[var(--radius-sm)] overflow-hidden bg-[var(--surface)] border border-[var(--rule)] shrink-0">
                    <img src="{{ asset('images/landing/fashion-studio.svg') }}" alt="Thumbnail Alte Heavyweight Graphic Tee" width="40" height="40" loading="lazy" class="w-full h-full object-cover">
                  </div>
                  <div>
                    <p class="font-bold text-[var(--ink)]">Alte Heavyweight Graphic Tee</p>
                    <p class="t-meta">Size: M · Washed Black · Qty: 1</p>
                  </div>
                </div>
                <span class="font-bold text-[var(--ink)] tabular-nums">₦18,500</span>
              </div>

              <div class="py-3.5 flex items-center justify-between text-xs">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-[var(--radius-sm)] overflow-hidden bg-[var(--surface)] border border-[var(--rule)] shrink-0">
                    <img src="{{ asset('images/landing/food-home.svg') }}" alt="Thumbnail Minimalist Calfskin Daily Tote" width="40" height="40" loading="lazy" class="w-full h-full object-cover">
                  </div>
                  <div>
                    <p class="font-bold text-[var(--ink)]">Minimalist Calfskin Daily Tote</p>
                    <p class="t-meta">Chestnut Leather · Qty: 1</p>
                  </div>
                </div>
                <span class="font-bold text-[var(--ink)] tabular-nums">₦52,000</span>
              </div>
            </div>

            <!-- Price Calculation Stack -->
            <div class="space-y-2.5 pt-4 border-t border-[var(--rule)] text-xs">
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-2)]">Subtotal (2 items)</span>
                <span class="font-medium text-[var(--ink)] tabular-nums">₦70,500</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-2)]">Express Courier (Lagos Island / Lekki)</span>
                <span class="font-medium text-[var(--ink)] tabular-nums">₦3,500</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-[var(--ink-2)]">Processing Fee</span>
                <span class="font-medium text-[var(--brand-text)] tabular-nums">₦0</span>
              </div>
              <div class="flex items-center justify-between pt-3 border-t border-[var(--rule)]">
                <span class="text-sm font-bold text-[var(--ink)]">Total Amount</span>
                <span class="t-price-lg text-[var(--ink)]">₦74,000</span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 pt-4 border-t border-[var(--rule)] flex flex-col sm:flex-row gap-3">
              <button type="button" class="flex-1 h-11 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-xs hover:opacity-90 transition-opacity">
                Proceed to Checkout
              </button>
              <button type="button" class="h-11 px-4 rounded-[var(--radius)] bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] font-bold text-xs hover:bg-[var(--paper)] transition-colors">
                Order via WhatsApp Direct
              </button>
            </div>
          </div>

          <!-- Payment Status States (5 Columns) -->
          <div class="lg:col-span-5 space-y-4">
            <h3 class="text-sm font-bold text-[var(--ink)] mb-3">Payment Life-cycle States</h3>

            <!-- State 1: Active / Paid at Booking (Amber) -->
            <div class="p-4 rounded-[var(--radius-lg)] border border-amber-500/30 bg-amber-500/5">
              <div class="flex items-center gap-2 mb-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                <h4 class="text-xs font-bold text-[var(--ink)]">Payment Awaiting Confirmation</h4>
                <span class="t-meta ml-auto font-mono text-[11px]">₦74,000</span>
              </div>
              <p class="text-xs text-[var(--ink-2)] leading-relaxed">
                Payment is collected by the seller's own Paystack or Flutterwave account. mypage.ng does not hold it.
              </p>
            </div>

            <!-- State 2: Released / Completed (Emerald) -->
            <div class="p-4 rounded-[var(--radius-lg)] border border-[var(--brand-edge)] bg-[var(--brand-wash)]">
              <div class="flex items-center gap-2 mb-1.5">
                <svg class="w-4 h-4 text-[var(--brand-text)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <h4 class="text-xs font-bold text-[var(--ink)]">Payment Received</h4>
                <span class="t-meta ml-auto font-mono text-[11px]">Completed</span>
              </div>
              <p class="text-xs text-[var(--ink-2)] leading-relaxed">
                Paid on 15 Oct 2026. Settlement lands directly in the seller's own gateway account.
              </p>
            </div>

            <!-- State 3: Dispute Mediation (Red) with native <details> -->
            <details class="p-4 rounded-[var(--radius-lg)] border border-red-500/30 bg-red-500/5 group">
              <summary class="flex items-center justify-between cursor-pointer list-none">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-red-500" aria-hidden="true"></span>
                  <h4 class="text-xs font-bold text-[var(--ink)]">Payment Failed</h4>
                </div>
                <span class="text-xs text-[var(--ink-3)] group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="mt-3 pt-3 border-t border-red-500/20 text-xs text-[var(--ink-2)] space-y-2">
                <p>The buyer's card was declined. Nothing moved and the order stays unpaid.</p>
                <p class="t-meta">The buyer can retry with another card or bank transfer at any time.</p>
              </div>
            </details>
          </div>

        </div>
      </div>
    </section>

    <!-- SECTION 7: Reviews & Social Proof Engine -->
    <section id="reviews" class="section-y border-b border-[var(--rule)]">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">07. Social Proof</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Authentic Nigerian customer reviews</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            Real star ratings, distribution breakdown bars, verified buyer badges, and a zero-framework accessible review submission drawer.
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          <!-- Rating Summary Distribution (4 Columns) -->
          <div class="lg:col-span-4 bg-[var(--surface)] p-6 rounded-[var(--radius-lg)] border border-[var(--rule)]">
            <h3 class="text-sm font-bold text-[var(--ink)] mb-4">Customer Rating Score</h3>

            <div class="flex items-baseline gap-3 mb-6">
              <span class="text-4xl font-extrabold text-[var(--ink)] font-mono">4.92</span>
              <div>
                <div class="flex text-amber-500 text-sm" aria-label="Rating: 5 out of 5 stars">
                  ★★★★★
                </div>
                <span class="t-meta">Based on 142 verified orders</span>
              </div>
            </div>

            <!-- Distribution Bars -->
            <div class="space-y-2 text-xs">
              <div class="flex items-center gap-2">
                <span class="w-12 text-[var(--ink-3)] tabular-nums">5 stars</span>
                <div class="flex-1 h-2 rounded-full bg-[var(--paper)] overflow-hidden border border-[var(--rule)]">
                  <div class="h-full bg-amber-500 rounded-full" style="width: 88%"></div>
                </div>
                <span class="w-8 text-right font-medium text-[var(--ink)] tabular-nums">88%</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-12 text-[var(--ink-3)] tabular-nums">4 stars</span>
                <div class="flex-1 h-2 rounded-full bg-[var(--paper)] overflow-hidden border border-[var(--rule)]">
                  <div class="h-full bg-amber-500 rounded-full" style="width: 9%"></div>
                </div>
                <span class="w-8 text-right font-medium text-[var(--ink)] tabular-nums">9%</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-12 text-[var(--ink-3)] tabular-nums">3 stars</span>
                <div class="flex-1 h-2 rounded-full bg-[var(--paper)] overflow-hidden border border-[var(--rule)]">
                  <div class="h-full bg-amber-500 rounded-full" style="width: 3%"></div>
                </div>
                <span class="w-8 text-right font-medium text-[var(--ink)] tabular-nums">3%</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-12 text-[var(--ink-3)] tabular-nums">2 stars</span>
                <div class="flex-1 h-2 rounded-full bg-[var(--paper)] overflow-hidden border border-[var(--rule)]">
                  <div class="h-full bg-amber-500 rounded-full" style="width: 0%"></div>
                </div>
                <span class="w-8 text-right font-medium text-[var(--ink)] tabular-nums">0%</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="w-12 text-[var(--ink-3)] tabular-nums">1 star</span>
                <div class="flex-1 h-2 rounded-full bg-[var(--paper)] overflow-hidden border border-[var(--rule)]">
                  <div class="h-full bg-amber-500 rounded-full" style="width: 0%"></div>
                </div>
                <span class="w-8 text-right font-medium text-[var(--ink)] tabular-nums">0%</span>
              </div>
            </div>

            <button type="button" 
                    @click="reviewModalOpen = true"
                    class="w-full mt-6 h-11 rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] hover:border-[var(--brand-edge)] text-xs font-bold transition-colors">
              Write a Review
            </button>
          </div>

          <!-- Review Cards Feed (8 Columns) -->
          <div class="lg:col-span-8 space-y-4">
            
            <!-- Review 1 -->
            <article class="p-5 rounded-[var(--radius-lg)] bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center font-bold text-xs text-[var(--ink)]">
                    TO
                  </div>
                  <div>
                    <h4 class="text-xs font-bold text-[var(--ink)]">Titilayo Olanrewaju</h4>
                    <p class="t-meta">Lekki Phase 1, Lagos</p>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-amber-500 text-xs">★★★★★</div>
                  <span class="t-meta text-[11px]">3 days ago</span>
                </div>
              </div>

              <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-[var(--brand-text)] bg-[var(--brand-wash)] px-2 py-0.5 rounded-[var(--radius-sm)] mb-2">
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                Verified Buyer (Order #MP-8831)
              </div>

              <p class="text-xs text-[var(--ink-2)] leading-relaxed">
                "The fabric quality exceeded my expectations. Delivered exactly 24 hours after WhatsApp confirmation. The Aso-Oke weaving has rich weight and didn't fade after the dry cleaners. I was nervous about paying upfront, but checkout was straightforward and the order arrived exactly as shown."
              </p>
            </article>

            <!-- Review 2 -->
            <article class="p-5 rounded-[var(--radius-lg)] bg-[var(--card)] border border-[var(--rule)] shadow-[var(--shadow-rest)]">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-[var(--surface)] border border-[var(--rule)] flex items-center justify-center font-bold text-xs text-[var(--ink)]">
                    EB
                  </div>
                  <div>
                    <h4 class="text-xs font-bold text-[var(--ink)]">Emeka Bello</h4>
                    <p class="t-meta">Wuse 2, Abuja</p>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-amber-500 text-xs">★★★★★</div>
                  <span class="t-meta text-[11px]">1 week ago</span>
                </div>
              </div>

              <div class="inline-flex items-center gap-1 text-[11px] font-semibold text-[var(--brand-text)] bg-[var(--brand-wash)] px-2 py-0.5 rounded-[var(--radius-sm)] mb-2">
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                Verified Buyer (Order #MP-8740)
              </div>

              <p class="text-xs text-[var(--ink-2)] leading-relaxed">
                "Solid leather craftsmanship from the Kano guild. Packed in a custom dust bag with leather balm. Interstate delivery from Kano to Abuja took just 2 days. Seamless experience without needing an app or passwords."
              </p>
            </article>

          </div>

        </div>
      </div>
    </section>

    <!-- SECTION 8: Accessible 3-Column Marquee (WCAG 2.2.2 Compliant) -->
    <section id="marquee" class="section-y border-b border-[var(--rule)] overflow-hidden">
      <div class="shell">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
          <div>
            <p class="t-meta">08. Discovery Marquee</p>
            <h2 class="t-display-2 text-[var(--ink)] mt-2">Verified storefronts nationwide</h2>
            <p class="t-body text-[var(--ink-2)] t-measure mt-2">
              3-column asynchronous marquee (up/down/up, edge mask-image fades).
              Strictly compliant with WCAG 2.2.2 via a visible pause/play toggle and static fallback under reduced motion.
            </p>
          </div>

          <!-- Mandatory WCAG 2.2.2 Pause/Play Button -->
          <button type="button" 
                  @click="marqueePaused = !marqueePaused"
                  class="self-start sm:self-auto inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-[var(--radius)] bg-[var(--surface)] text-[var(--ink)] border border-[var(--rule)] hover:bg-[var(--paper)] transition-colors"
                  :aria-label="marqueePaused ? 'Resume scrolling storefront marquee' : 'Pause scrolling storefront marquee'">
            <span class="w-2 h-2 rounded-full" :class="marqueePaused ? 'bg-amber-500' : 'bg-[var(--brand)]'" aria-hidden="true"></span>
            <span x-text="marqueePaused ? 'Resume Marquee' : 'Pause Marquee'">Pause Marquee</span>
          </button>
        </div>

        <!-- Desktop 3-Column Marquee (36rem height, edge mask fades) -->
        <div class="hidden lg:grid grid-cols-3 gap-6 h-[36rem] marquee" :class="{ 'is-paused': marqueePaused }">
          
          <!-- Column 1: Upward Scroll (48s) -->
          <div class="marquee-track-up">
            <!-- Set 1 -->
            <div class="card p-4">
              <span class="t-meta">Lagos Fashion</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Alte Studios Lekki</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Streetwear & Graphic Silhouettes</p>
            </div>
            <div class="card p-4">
              <span class="t-meta">Abuja Gourmet</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Maitama Artisanal Patisserie</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Bespoke Wedding Cakes</p>
            </div>
            <div class="card p-4">
              <span class="t-meta">Kano Crafts</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Kano Leather Guild</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Hand-tanned calfskin accessories</p>
            </div>
            <!-- Duplicate Set for Seamless Loop (marked inert for screen-readers) -->
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Lagos Fashion</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Alte Studios Lekki</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Streetwear & Graphic Silhouettes</p>
            </div>
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Abuja Gourmet</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Maitama Artisanal Patisserie</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Bespoke Wedding Cakes</p>
            </div>
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Kano Crafts</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Kano Leather Guild</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Hand-tanned calfskin accessories</p>
            </div>
          </div>

          <!-- Column 2: Downward Scroll (56s) - offset -6rem -->
          <div class="marquee-track-down -mt-24">
            <div class="card p-4">
              <span class="t-meta">Port Harcourt Tech</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Niger Delta Lens Studio</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Industrial & Corporate Cinematography</p>
            </div>
            <div class="card p-4">
              <span class="t-meta">Ibadan Living</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Oyo Terracotta Ceramics</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Hand-thrown earthen homewares</p>
            </div>
            <div class="card p-4">
              <span class="t-meta">Enugu Apparel</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Coal City Tailoring Co.</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Formal linen safari suits</p>
            </div>
            <!-- Duplicate Set -->
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Port Harcourt Tech</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Niger Delta Lens Studio</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Industrial & Corporate Cinematography</p>
            </div>
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Ibadan Living</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Oyo Terracotta Ceramics</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Hand-thrown earthen homewares</p>
            </div>
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Enugu Apparel</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Coal City Tailoring Co.</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Formal linen safari suits</p>
            </div>
          </div>

          <!-- Column 3: Upward Scroll (48s) -->
          <div class="marquee-track-up">
            <div class="card p-4">
              <span class="t-meta">Lagos Beauty</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Gele by Bisi</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Traditional auto-gele couture</p>
            </div>
            <div class="card p-4">
              <span class="t-meta">Kaduna Silks</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Arewa Weavers Co.</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Authentic northern dyed damask</p>
            </div>
            <div class="card p-4">
              <span class="t-meta">Benin Arts</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Igun Bronze Foundry</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Heritage lost-wax sculptures</p>
            </div>
            <!-- Duplicate Set -->
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Lagos Beauty</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Gele by Bisi</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Traditional auto-gele couture</p>
            </div>
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Kaduna Silks</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Arewa Weavers Co.</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Authentic northern dyed damask</p>
            </div>
            <div class="card p-4" aria-hidden="true" inert>
              <span class="t-meta">Benin Arts</span>
              <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Igun Bronze Foundry</h4>
              <p class="text-xs text-[var(--ink-2)] mt-1">Heritage lost-wax sculptures</p>
            </div>
          </div>

        </div>

        <!-- Mobile / Tablet Fallback (<lg): Clean Static 2-Column Grid -->
        <div class="lg:hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="card p-4">
            <span class="t-meta">Lagos Fashion</span>
            <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Alte Studios Lekki</h4>
            <p class="text-xs text-[var(--ink-2)] mt-1">Streetwear & Graphic Silhouettes</p>
          </div>
          <div class="card p-4">
            <span class="t-meta">Abuja Gourmet</span>
            <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Maitama Artisanal Patisserie</h4>
            <p class="text-xs text-[var(--ink-2)] mt-1">Bespoke Wedding Cakes</p>
          </div>
          <div class="card p-4">
            <span class="t-meta">Kano Crafts</span>
            <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Kano Leather Guild</h4>
            <p class="text-xs text-[var(--ink-2)] mt-1">Hand-tanned calfskin accessories</p>
          </div>
          <div class="card p-4">
            <span class="t-meta">Port Harcourt Tech</span>
            <h4 class="text-sm font-bold text-[var(--ink)] mt-1">Niger Delta Lens Studio</h4>
            <p class="text-xs text-[var(--ink-2)] mt-1">Industrial & Corporate Cinematography</p>
          </div>
        </div>

      </div>
    </section>

    <!-- SECTION 9: Persistent Chrome Demonstration -->
    <section id="chrome" class="section-y">
      <div class="shell">
        <div class="mb-10">
          <p class="t-meta">09. Persistent WhatsApp Chrome</p>
          <h2 class="t-display-2 text-[var(--ink)] mt-2">Always-on customer bridge</h2>
          <p class="t-body text-[var(--ink-2)] t-measure mt-2">
            In Nigeria, commerce converts on WhatsApp. Mobile users get an ergonomic fixed bottom bar respecting safe areas;
            desktop users receive an unobtrusive vertical right-edge tab.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          <!-- Mobile Bottom Bar Demo Preview -->
          <div class="p-6 rounded-[var(--radius-lg)] bg-[var(--surface)] border border-[var(--rule)]">
            <h3 class="text-sm font-bold text-[var(--ink)] mb-2">Mobile Bottom Bar (360px–768px)</h3>
            <p class="t-meta mb-4">Positioned at bottom with safe-area padding for gestures.</p>

            <div class="rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] p-3 shadow-md">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <span class="text-[11px] text-[var(--ink-3)] block">Merchant Online</span>
                  <span class="text-xs font-bold text-[var(--ink)]">Adeola Bridal Studio</span>
                </div>
                <a href="https://wa.me/2348000000000" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="h-10 px-4 rounded-[var(--radius)] bg-[#25D366] text-white text-xs font-bold flex items-center gap-1.5 shadow-sm hover:opacity-95">
                  <x-ui.whatsapp-mark />
                  <span>Chat on WhatsApp</span>
                </a>
              </div>
            </div>
          </div>

          <!-- Desktop Vertical Tab Demo Preview -->
          <div class="p-6 rounded-[var(--radius-lg)] bg-[var(--surface)] border border-[var(--rule)]">
            <h3 class="text-sm font-bold text-[var(--ink)] mb-2">Desktop Edge Tab (≥1024px)</h3>
            <p class="t-meta mb-4">Vertical text tab anchored to the right viewport border.</p>

            <div class="flex items-center justify-center p-6 bg-[var(--paper)] rounded-[var(--radius)] border border-[var(--rule)]">
              <a href="https://wa.me/2348000000000" 
                 target="_blank" 
                 rel="noopener noreferrer" 
                 class="inline-flex items-center gap-2 py-3 px-2 rounded-l-[var(--radius)] bg-[#25D366] text-white text-xs font-bold shadow-md hover:translate-x-[-2px] transition-transform"
                 style="writing-mode: vertical-rl;">
                <x-ui.whatsapp-mark :rotate="true" />
                <span>WhatsApp Concierge</span>
              </a>
            </div>
          </div>

        </div>
      </div>
    </section>

  </main>

  <!-- Write Review Modal (Inline Alpine Dialog) -->
  <div x-show="reviewModalOpen" 
       x-transition.opacity.duration.200ms
       @keydown.escape.window="reviewModalOpen = false"
       class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" 
       style="display: none;"
       role="dialog" 
       aria-modal="true" 
       aria-labelledby="review-dialog-title">
    
    <div @click.away="reviewModalOpen = false" 
         class="w-full max-w-lg bg-[var(--card)] p-6 sm:p-8 rounded-[var(--radius-lg)] border border-[var(--rule)] shadow-[var(--shadow-lift)]">
      
      <div class="flex items-center justify-between pb-3 border-b border-[var(--rule)] mb-4">
        <h3 id="review-dialog-title" class="text-base font-bold text-[var(--ink)]">Write a Customer Review</h3>
        <button type="button" @click="reviewModalOpen = false" class="text-[var(--ink-3)] hover:text-[var(--ink)] text-sm font-bold p-1" aria-label="Close review dialog">✕</button>
      </div>

      <form action="#submit-review" method="POST" @submit.prevent="reviewModalOpen = false" class="space-y-4">
        <div>
          <label for="review-order-ref" class="block text-xs font-bold text-[var(--ink)] mb-1">
            Order Reference Number
          </label>
          <input type="text" id="review-order-ref" placeholder="e.g. MP-LAG-9842" required class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)] font-mono">
          <p class="t-meta mt-1">Found on your WhatsApp confirmation receipt.</p>
        </div>

        <div>
          <label for="review-rating" class="block text-xs font-bold text-[var(--ink)] mb-1">
            Rating Score
          </label>
          <select id="review-rating" class="w-full h-11 px-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)]">
            <option value="5">★★★★★ — Exceptional craftsmanship & delivery</option>
            <option value="4">★★★★☆ — Very good experience</option>
            <option value="3">★★★☆☆ — Met standard expectations</option>
          </select>
        </div>

        <div>
          <label for="review-body" class="block text-xs font-bold text-[var(--ink)] mb-1">
            Your Review
          </label>
          <textarea id="review-body" rows="3" placeholder="Describe the product fit, fabric quality, and packaging..." required class="w-full p-3 text-sm rounded-[var(--radius)] bg-[var(--paper)] text-[var(--ink)] border border-[var(--rule)]"></textarea>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-[var(--rule)]">
          <button type="button" @click="reviewModalOpen = false" class="px-4 py-2 text-xs font-bold text-[var(--ink-2)] hover:text-[var(--ink)]">Cancel</button>
          <button type="submit" class="px-5 py-2 text-xs font-bold rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)]">Submit Verified Review</button>
        </div>
      </form>
    </div>
  </div>
    </div>
@endsection
