<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts

        <!-- Styles / Scripts -->
            @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body >
     
    <!-- Top Bar: Nigerian vendor trust banner -->
    <div class="bg-ink text-slate-200 text-xs py-2 px-4 border-b border-slate-800">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-brand animate-pulse"></span>
          <span class="font-medium text-slate-100">Live in Nigeria:</span>
          <span class="text-slate-300">Over ₦480,000,000 processed for 12,400+ sellers across Lagos, Aba, Onitsha & Abuja.</span>
        </div>
        <div class="flex items-center gap-4 text-slate-300">
          <span class="inline-flex items-center gap-1.5 text-slate-400">
            <svg class="w-3.5 h-3.5 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
            Paystack & Flutterwave Verified
          </span>
          <span class="hidden md:inline text-slate-600">|</span>
          <span class="hidden md:inline text-slate-400">WhatsApp Business Solution Compliant</span>
        </div>
      </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
        <!-- Logo -->
        <a href="/" class="flex items-center gap-2.5 group">
          <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm transition-transform group-hover:scale-105">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <!-- Custom WhatsApp + shopping bag symbol -->
              <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-1.5">
              <span class="font-display font-extrabold text-xl tracking-tight text-ink">mypage<span class="text-brand">.ng</span></span>
              <span class="text-[10px] font-bold uppercase tracking-wider bg-brand-soft text-brand-dark px-1.5 py-0.5 rounded">NG</span>
            </div>
            <p class="text-[10px] text-body leading-none hidden sm:block">WhatsApp Commerce Suite</p>
          </div>
        </a>

        <!-- Desktop Navigation Links -->
        <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-body">
          <a href="#how-it-works" class="hover:text-brand transition-colors">How it works</a>
          <a href="#features" class="hover:text-brand transition-colors">Features</a>
          <a href="#" class="hover:text-brand transition-colors">Pricing</a>
          <a href="#" class="hover:text-brand transition-colors flex items-center gap-1.5">
            <span>Verified Sellers</span>
            <span class="w-2 h-2 rounded-full bg-brand"></span>
          </a>
          <a href="#" class="hover:text-brand transition-colors">Demo Store</a>
          <a href="#faq" class="hover:text-brand transition-colors">FAQ</a>
        </nav>

        <!-- Action CTAs -->
        <div class="flex items-center gap-3">
          <a href="/login" class="hidden sm:inline-flex text-sm font-medium text-slate-700 hover:text-ink px-3 py-2 rounded-lg hover:bg-slate-100 transition-colors">
            Log in
          </a>
          <a href="/dashboard" class="hidden lg:inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-2 rounded-lg transition-colors border border-slate-200">
            <svg class="w-3.5 h-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Seller Demo
          </a>
          <a href="/register" class="inline-flex items-center justify-center gap-2 bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-sm hover:shadow transition-all group">
            <span>Claim Your Link</span>
            <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
          </a>

          <!-- Mobile menu button -->
          <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 focus:outline-none" aria-label="Toggle navigation menu">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
          </button>
        </div>
      </div>

      <!-- Mobile dropdown menu -->
      <div id="mobile-nav" class="hidden md:hidden border-b border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3">
        <a href="#how-it-works" class="block py-2 text-sm font-medium text-slate-700 hover:text-brand">How it works</a>
        <a href="#features" class="block py-2 text-sm font-medium text-slate-700 hover:text-brand">Features</a>
        <a href="#" class="block py-2 text-sm font-medium text-slate-700 hover:text-brand">Pricing (₦3,500/mo Pro)</a>
        <a href="#" class="block py-2 text-sm font-medium text-slate-700 hover:text-brand">Verified Sellers Directory</a>
        <a href="#" class="block py-2 text-sm font-medium text-slate-700 hover:text-brand">Live Demo Store</a>
        <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
          <a href="/dashboard" class="w-full text-center py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-sm font-medium text-slate-800">Explore Seller Dashboard</a>
          <a href="/login" class="w-full text-center py-2.5 rounded-lg border border-slate-200 text-sm font-medium text-slate-700">Log in</a>
          <a href="/register" class="w-full text-center py-2.5 rounded-lg bg-brand text-white font-semibold text-sm">Create Store Free</a>
        </div>
      </div>
    </header>

    <!-- Quick Navigation Bar for Demo Showcase (Allows testing all pages) -->
    <div class="bg-emerald-950 text-emerald-200 border-b border-emerald-900 py-2 px-4 text-xs">
      <div class="max-w-7xl mx-auto flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
          <span class="font-semibold text-white">Full Platform Preview:</span>
          <span class="text-emerald-300 hidden sm:inline">Jump straight into any role or page</span>
        </div>
        <div class="flex items-center gap-3 overflow-x-auto py-1">
          <a href="/dashboard" class="text-white hover:text-emerald-300 font-medium underline underline-offset-4">Seller Dashboard</a>
          <span class="text-emerald-700">·</span>
          <a href="#" class="text-white hover:text-emerald-300 font-medium underline underline-offset-4">Page Builder</a>
          <span class="text-emerald-700">·</span>
          <a href="#" class="text-white hover:text-emerald-300 font-medium underline underline-offset-4">Order Kanban</a>
          <span class="text-emerald-700">·</span>
          <a href="#" class="text-white hover:text-emerald-300 font-medium underline underline-offset-4">Buyer Storefront</a>
          <span class="text-emerald-700">·</span>
          <a href="#" class="text-white hover:text-emerald-300 font-medium underline underline-offset-4">Escrow Checkout</a>
          <span class="text-emerald-700">·</span>
          <a href="#" class="text-white hover:text-emerald-300 font-medium underline underline-offset-4">Admin Hub</a>
        </div>
      </div>
    </div>

    <!-- HERO SECTION -->
    <section class="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-28 bg-gradient-to-b from-white via-surface to-slate-100 border-b border-slate-200">
      <!-- Background subtle grid -->
      <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f01a_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f01a_1px,transparent_1px)] bg-[size:3rem_3rem] pointer-events-none"></div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
          
          <!-- Hero Copy Left (7 cols) -->
          <div class="lg:col-span-7 text-center lg:text-left space-y-6">
            
            <!-- WhatsApp Pill badge -->
            <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-brand-soft border border-emerald-200 text-brand-dark text-xs font-semibold shadow-sm">
              <span class="flex h-2 w-2 relative">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-brand"></span>
              </span>
              <span>WhatsApp-First Commerce for Nigeria</span>
              <span class="text-slate-400">·</span>
              <span class="text-emerald-800">Zero Commission on Free Plan</span>
            </div>

            <!-- Headline -->
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-display font-extrabold tracking-tight text-ink leading-[1.12]">
              Your WhatsApp is a shop. <br class="hidden sm:inline" />
              <span class="text-brand">Start treating it like one.</span>
            </h1>

            <!-- Subhead -->
            <p class="text-lg sm:text-xl text-body leading-relaxed max-w-2xl mx-auto lg:mx-0">
              Stop sending 40 screenshots and asking <em class="text-ink font-semibold not-italic">"Have you sent the alert?"</em>. 
              Give your Nigerian buyers a clean, fast catalog that closes sales right inside WhatsApp with instant bank transfer, debit cards, or escrow protection.
            </p>

            <!-- Store name claimer input -->
            <div class="pt-2 max-w-xl mx-auto lg:mx-0">
              <form id="claim-store-form" class="bg-white p-2 rounded-2xl border-2 border-slate-200 shadow-md focus-within:border-brand focus-within:ring-4 focus-within:ring-brand/10 transition-all flex flex-col sm:flex-row items-stretch gap-2">
                <div class="flex items-center flex-1 px-3 py-2 text-slate-500">
                  <span class="text-xs sm:text-sm font-semibold text-slate-400 select-none">mypage.ng/</span>
                  <input 
                    type="text" 
                    id="store-handle-input" 
                    placeholder="yourbrandname" 
                    value="amaka" 
                    class="w-full bg-transparent border-none text-ink font-semibold text-sm sm:text-base focus:outline-none placeholder:text-slate-400 pl-1"
                    required
                  />
                </div>
                <button 
                  type="submit" 
                  class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm sm:text-base px-6 py-3.5 rounded-xl transition-all flex items-center justify-center gap-2 whitespace-nowrap shadow-sm group"
                >
                  <span>Create Free Shop</span>
                  <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
              </form>
              <div class="flex items-center justify-between text-xs text-slate-500 mt-2.5 px-2">
                <span class="flex items-center gap-1.5 text-emerald-700 font-medium">
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                  Free forever plan available
                </span>
                <span>No laptop required · Ready in 60s</span>
              </div>
            </div>

            <!-- Trust stats bar -->
            <div class="pt-6 border-t border-slate-200/80 grid grid-cols-3 gap-4 text-center lg:text-left">
              <div>
                <p class="font-display text-2xl sm:text-3xl font-extrabold text-ink tabular-naira">12,400+</p>
                <p class="text-xs text-body font-medium mt-0.5">Active Nigerian Vendors</p>
              </div>
              <div>
                <p class="font-display text-2xl sm:text-3xl font-extrabold text-brand tabular-naira">₦480M+</p>
                <p class="text-xs text-body font-medium mt-0.5">Sales Processed</p>
              </div>
              <div>
                <p class="font-display text-2xl sm:text-3xl font-extrabold text-ink tabular-naira">99.2%</p>
                <p class="text-xs text-body font-medium mt-0.5">WhatsApp Completion</p>
              </div>
            </div>

          </div>

          <!-- Hero Right: Interactive Mobile Mockup inside WhatsApp (5 cols) -->
          <div class="lg:col-span-5 flex justify-center">
            <div class="relative w-full max-w-[340px] sm:max-w-[380px]">
              
              <!-- Subtle glow background -->
              <div class="absolute -inset-4 bg-gradient-to-r from-emerald-500/20 to-teal-500/20 rounded-[3rem] blur-2xl -z-10"></div>

              <!-- Phone Frame -->
              <div class="bg-slate-900 p-3 sm:p-4 rounded-[2.8rem] shadow-2xl border-4 border-slate-800">
                <!-- Phone Speaker / Dynamic Island -->
                <div class="w-32 h-4 bg-slate-800 rounded-full mx-auto mb-3 flex items-center justify-center">
                  <div class="w-3 h-3 rounded-full bg-slate-900 border border-slate-700"></div>
                </div>

                <!-- Phone Screen Content (WhatsApp Chat Simulation + Store Card) -->
                <div class="bg-[#ECE5DD] rounded-[2rem] overflow-hidden text-slate-800 text-xs shadow-inner flex flex-col h-[560px]">
                  
                  <!-- WhatsApp Chat Header -->
                  <div class="bg-[#075E54] text-white px-3.5 py-3 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded-full bg-white/20 overflow-hidden relative border border-white/30">
                        <img src="https://picsum.photos/seed/fashionlagos/100/100" alt="Amaka Stitches" class="w-full h-full object-cover"/>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 border border-[#075E54]"></span>
                      </div>
                      <div>
                        <div class="flex items-center gap-1">
                          <span class="font-semibold text-white text-xs">Amaka Stitches</span>
                          <svg class="w-3.5 h-3.5 text-emerald-300" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        </div>
                        <p class="text-[10px] text-emerald-100 flex items-center gap-1">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
                          Online · mypage.ng verified
                        </p>
                      </div>
                    </div>
                    <div class="flex items-center gap-3 text-emerald-100">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </div>
                  </div>

                  <!-- WhatsApp Chat Scroll Area -->
                  <div class="flex-1 p-3 overflow-y-auto space-y-3 font-sans">
                    
                    <!-- Chat timestamp badge -->
                    <div class="text-center">
                      <span class="bg-white/80 shadow-xs text-slate-600 text-[10px] font-medium px-2.5 py-1 rounded-md">Today</span>
                    </div>

                    <!-- Customer message -->
                    <div class="flex justify-start">
                      <div class="bg-white rounded-lg rounded-tl-none p-2.5 max-w-[85%] shadow-xs space-y-1">
                        <p class="text-[11px] text-slate-800">Hello sis! Do you have the Emerald Silk Boubou in size M? How much?</p>
                        <div class="text-[9px] text-slate-400 text-right">10:14 AM</div>
                      </div>
                    </div>

                    <!-- Seller automated card (mypage.ng link) -->
                    <div class="flex justify-end">
                      <div class="bg-[#DCF8C6] rounded-lg rounded-tr-none p-2.5 max-w-[92%] shadow-xs space-y-2">
                        <p class="text-[11px] text-slate-800">Hi dear! Yes, in stock. You can order directly & pick size here:</p>
                        
                        <!-- Rich Link Preview Card -->
                        <div class="bg-white rounded-lg overflow-hidden border border-emerald-200 shadow-sm text-left">
                          <div class="relative h-28 w-full bg-slate-100">
                            <img src="https://picsum.photos/seed/bouboufashion/400/250" alt="Silk Boubou" class="w-full h-full object-cover"/>
                            <span class="absolute top-2 right-2 bg-emerald-600 text-white font-bold text-[10px] px-2 py-0.5 rounded-full shadow">
                              In Stock (Lagos)
                            </span>
                          </div>
                          <div class="p-2.5 space-y-1">
                            <div class="flex items-center justify-between">
                              <h4 class="font-bold text-slate-900 text-xs">Emerald Silk Boubou Dress</h4>
                              <span class="text-brand font-bold text-xs tabular-naira">₦24,500</span>
                            </div>
                            <p class="text-[10px] text-slate-500 line-clamp-1">Pure silk blend with hand-stitched neckline detailing.</p>
                            
                            <div class="pt-2 flex items-center justify-between">
                              <div class="flex items-center gap-1 text-[10px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                <span>🛡️</span>
                                <span>Escrow Protected</span>
                              </div>
                              <a href="#" class="bg-brand text-white font-semibold text-[10px] px-2.5 py-1 rounded hover:bg-brand-dark transition-colors">
                                View & Order →
                              </a>
                            </div>
                          </div>
                        </div>

                        <div class="flex items-center justify-end gap-1 text-[9px] text-slate-500">
                          <span>10:15 AM</span>
                          <svg class="w-3.5 h-3.5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L7 17l-5-5"/><path d="M22 10l-7.5 7.5-1.5-1.5"/></svg>
                        </div>
                      </div>
                    </div>

                    <!-- Instant Automated Order Confirmation in Chat -->
                    <div class="flex justify-end">
                      <div class="bg-[#DCF8C6] rounded-lg rounded-tr-none p-2.5 max-w-[92%] shadow-xs space-y-1.5 border-l-4 border-emerald-600">
                        <div class="flex items-center justify-between border-b border-emerald-200/60 pb-1">
                          <span class="font-bold text-slate-900 text-[10px]">🎉 New Order #MP-8921</span>
                          <span class="bg-emerald-600 text-white text-[9px] font-bold px-1.5 py-0.2 rounded">PAID</span>
                        </div>
                        <p class="text-[10px] text-slate-700">Customer: <strong class="text-slate-900">Chioma Okeke (Lekki Phase 1)</strong></p>
                        <p class="text-[10px] text-slate-700">1x Emerald Silk Boubou (Size M) · ₦24,500</p>
                        <p class="text-[9px] text-emerald-800 font-medium">Payment verified via Paystack. Funds secured in Escrow.</p>
                      </div>
                    </div>

                  </div>

                  <!-- WhatsApp Footer Bar -->
                  <div class="bg-[#F0F2F5] px-3 py-2 border-t border-slate-300 flex items-center gap-2">
                    <button class="text-slate-500 hover:text-slate-700 p-1">
                      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </button>
                    <div class="flex-1 bg-white rounded-full px-3 py-1.5 text-[11px] text-slate-400 border border-slate-200">
                      Type a message...
                    </div>
                    <div class="w-7 h-7 rounded-full bg-[#075E54] text-white flex items-center justify-center">
                      <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Floating badge: Instant notification -->
              <div class="absolute -bottom-4 -left-4 sm:-left-8 bg-white p-3 rounded-xl shadow-lg border border-slate-200 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-brand flex items-center justify-center font-bold">
                  ₦
                </div>
                <div>
                  <p class="text-xs font-semibold text-ink">Bank Alert Received</p>
                  <p class="text-[11px] text-slate-500"><span class="text-brand font-bold">₦24,500</span> credited into wallet</p>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- SOCIAL PROOF STRIP -->
    <section class="py-10 bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-500 mb-6">
          Powering Nigeria's top social commerce hubs & markets
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-6 items-center justify-items-center opacity-75 grayscale hover:grayscale-0 transition-all">
          <div class="flex items-center gap-2 font-display font-bold text-slate-700 text-sm">
            <span class="w-3 h-3 rounded-full bg-brand"></span> Balogun Market
          </div>
          <div class="flex items-center gap-2 font-display font-bold text-slate-700 text-sm">
            <span class="w-3 h-3 rounded-full bg-brand"></span> Ariaria Aba
          </div>
          <div class="flex items-center gap-2 font-display font-bold text-slate-700 text-sm">
            <span class="w-3 h-3 rounded-full bg-brand"></span> Computer Village
          </div>
          <div class="flex items-center gap-2 font-display font-bold text-slate-700 text-sm">
            <span class="w-3 h-3 rounded-full bg-brand"></span> Wuse 2 Abuja
          </div>
          <div class="flex items-center gap-2 font-display font-bold text-slate-700 text-sm">
            <span class="w-3 h-3 rounded-full bg-brand"></span> Main Market Onitsha
          </div>
          <div class="flex items-center gap-2 font-display font-bold text-slate-700 text-sm">
            <span class="w-3 h-3 rounded-full bg-brand"></span> Lekki Artisans
          </div>
        </div>
      </div>
    </section>

    <!-- 3-STEP HOW IT WORKS -->
    <section id="how-it-works" class="py-20 bg-surface border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <span class="text-brand font-semibold text-xs uppercase tracking-wider bg-brand-soft px-3 py-1 rounded-full">Simple 3-Step Setup</span>
          <h2 class="text-3xl sm:text-4xl font-display font-extrabold text-ink">
            Sell faster on WhatsApp without the headache
          </h2>
          <p class="text-base sm:text-lg text-body">
            You don't need a heavy website or a laptop. Setup takes under 60 seconds directly from your Android phone or iPhone.
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 relative">
          
          <!-- Step 1 -->
          <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative">
            <div class="w-12 h-12 rounded-xl bg-brand-soft text-brand-dark flex items-center justify-center font-display font-extrabold text-xl mb-6">
              1
            </div>
            <h3 class="text-xl font-display font-bold text-ink mb-2">Create Your Page</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Upload your products, set prices in ₦, add your sizes and colors. Choose between Minimal, Bold, or Warm themes.
            </p>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex items-center gap-2 text-xs text-slate-600 font-medium">
              <svg class="w-4 h-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
              <span>Add products or sync existing photos</span>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-display font-extrabold text-xl mb-6">
              2
            </div>
            <h3 class="text-xl font-display font-bold text-ink mb-2">Share Your Link</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Paste <span class="font-semibold text-ink">mypage.ng/yourshop</span> in your WhatsApp Status, Instagram Bio, TikTok, or direct message chats.
            </p>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex items-center gap-2 text-xs text-slate-600 font-medium">
              <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
              <span>Auto-generates wa.me deep links</span>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative">
            <div class="w-12 h-12 rounded-xl bg-accent-soft text-accent-dark flex items-center justify-center font-display font-extrabold text-xl mb-6">
              3
            </div>
            <h3 class="text-xl font-display font-bold text-ink mb-2">Get Paid Safely</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Customers pay via Instant Bank Transfer, USSD, or Card. Optional Escrow protection gives first-time buyers 100% peace of mind.
            </p>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 flex items-center gap-2 text-xs text-slate-600 font-medium">
              <svg class="w-4 h-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
              <span>Instant alerts to your Opay/Palmpay/GTBank</span>
            </div>
          </div>

        </div>

        <div class="mt-12 text-center">
          <a href="/onboarding/business" class="inline-flex items-center gap-2 bg-ink hover:bg-slate-800 text-white font-semibold text-sm px-6 py-3.5 rounded-xl shadow transition-colors">
            <span>Try the 60-Second Setup Wizard</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
          </a>
        </div>

      </div>
    </section>

    <!-- CORE FEATURES GRID -->
    <section id="features" class="py-20 bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <span class="text-brand font-semibold text-xs uppercase tracking-wider bg-brand-soft px-3 py-1 rounded-full">Built for Nigerian Realities</span>
          <h2 class="text-3xl sm:text-4xl font-display font-extrabold text-ink">
            Everything your business needs to scale
          </h2>
          <p class="text-base sm:text-lg text-body">
            Tailored specifically for Nigerian logistics, network bandwidth, fake alert defense, and WhatsApp customer retention.
          </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          
          <!-- Feature 1: Lightning Fast Page Builder -->
          <div class="p-6 rounded-2xl border border-slate-200 hover:border-brand/40 bg-white hover:bg-slate-50/50 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-brand-soft text-brand-dark flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-ink mb-2">Block Page Builder</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Reorder Hero, Catalog, Reviews, and WhatsApp buttons with drag-and-drop. Optimized for 3G/4G connections on MTN and Airtel.
            </p>
            <a href="#" class="text-xs font-semibold text-brand hover:underline flex items-center gap-1">
              Explore Page Builder →
            </a>
          </div>

          <!-- Feature 2: Anti-Fake Alert & Escrow -->
          <div class="p-6 rounded-2xl border border-slate-200 hover:border-brand/40 bg-white hover:bg-slate-50/50 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-accent-soft text-accent-dark flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-ink mb-2">Zero Fake Alerts & Escrow</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Never fall for edited bank alert screenshots again. Payments are reconciled automatically. Buyers can lock funds in escrow until delivery is verified.
            </p>
            <a href="#" class="text-xs font-semibold text-accent hover:underline flex items-center gap-1">
              How Escrow Works →
            </a>
          </div>

          <!-- Feature 3: WhatsApp Pipeline Inbox -->
          <div class="p-6 rounded-2xl border border-slate-200 hover:border-brand/40 bg-white hover:bg-slate-50/50 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-ink mb-2">Order Kanban Pipeline</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Move orders from <span class="font-semibold text-slate-800">New → Paid → Processing → Shipped → Delivered</span>. Trigger 1-click status messages back to buyer.
            </p>
            <a href="#" class="text-xs font-semibold text-blue-700 hover:underline flex items-center gap-1">
              View Order Pipeline →
            </a>
          </div>

          <!-- Feature 4: Customer Book & LTV -->
          <div class="p-6 rounded-2xl border border-slate-200 hover:border-brand/40 bg-white hover:bg-slate-50/50 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-ink mb-2">Customer Book (CRM)</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Never lose a customer when your phone gets stolen or wiped. Auto-saves phone numbers, total ₦ spent, location, and previous sizes.
            </p>
            <a href="#" class="text-xs font-semibold text-indigo-700 hover:underline flex items-center gap-1">
              Browse Customer Book →
            </a>
          </div>

          <!-- Feature 5: Smart WhatsApp Broadcasts -->
          <div class="p-6 rounded-2xl border border-slate-200 hover:border-brand/40 bg-white hover:bg-slate-50/50 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-brand-dark flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-ink mb-2">Broadcasts with Live ₦ Rates</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Send restock alerts and promos to past buyers without getting banned by Meta. Live calculation of messaging cost in Naira.
            </p>
            <a href="#" class="text-xs font-semibold text-brand hover:underline flex items-center gap-1">
              Calculate Broadcast Costs →
            </a>
          </div>

          <!-- Feature 6: Payouts into Any Nigerian Bank -->
          <div class="p-6 rounded-2xl border border-slate-200 hover:border-brand/40 bg-white hover:bg-slate-50/50 transition-all group">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-ink mb-2">Same-Day Payouts</h3>
            <p class="text-sm text-body leading-relaxed mb-4">
              Withdraw straight into Opay, PalmPay, Moniepoint, GTBank, Zenith, Access, or Kuda. Automatic 6:00 PM settlement option.
            </p>
            <a href="#" class="text-xs font-semibold text-amber-700 hover:underline flex items-center gap-1">
              View Wallet & Settlements →
            </a>
          </div>

        </div>

      </div>
    </section>

    <!-- INTERACTIVE TEST DRIVE (DEMO PRODUCT QUICK CHECKOUT SIMULATOR) -->
    <section class="py-20 bg-slate-900 text-white relative overflow-hidden">
      <!-- Glow effect -->
      <div class="absolute top-0 right-1/4 w-96 h-96 bg-brand/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        
        <div class="grid lg:grid-cols-12 gap-12 items-center">
          
          <div class="lg:col-span-6 space-y-6">
            <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-400 bg-emerald-950/80 border border-emerald-800 px-3 py-1 rounded-full">
              <span>⚡</span> Interactive Storefront Demo
            </span>
            <h2 class="text-3xl sm:text-4xl font-display font-extrabold text-white leading-tight">
              See what your customer experiences in 3 clicks
            </h2>
            <p class="text-slate-300 text-base leading-relaxed">
              When a buyer taps your link, they see your branded storefront. Selecting items auto-formats a tidy WhatsApp checkout message or opens an instant Paystack gateway.
            </p>

            <div class="space-y-4 pt-2">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 rounded-full bg-brand/20 text-brand flex items-center justify-center text-xs font-bold mt-0.5">✓</div>
                <div>
                  <h4 class="font-semibold text-white text-sm">No app download needed for buyers</h4>
                  <p class="text-slate-400 text-xs">Opens instantly in WhatsApp's in-app browser or Chrome.</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 rounded-full bg-brand/20 text-brand flex items-center justify-center text-xs font-bold mt-0.5">✓</div>
                <div>
                  <h4 class="font-semibold text-white text-sm">Auto calculates delivery fee by Lagos LGA</h4>
                  <p class="text-slate-400 text-xs">Ikeja, Lekki, Yaba, Surulere or interstate delivery presets.</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 rounded-full bg-brand/20 text-brand flex items-center justify-center text-xs font-bold mt-0.5">✓</div>
                <div>
                  <h4 class="font-semibold text-white text-sm">WhatsApp pre-filled order draft</h4>
                  <p class="text-slate-400 text-xs">Sends item name, variant, address, and reference number automatically.</p>
                </div>
              </div>
            </div>

            <div class="pt-4 flex flex-wrap items-center gap-4">
              <a href="#" class="bg-brand hover:bg-brand-dark text-white font-semibold text-sm px-6 py-3.5 rounded-xl transition-all shadow-wa">
                View Full Live Store (Amaka Stitches)
              </a>
              <a href="#" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-semibold text-sm px-6 py-3.5 rounded-xl transition-all">
                Test Escrow Checkout
              </a>
            </div>

          </div>

          <!-- Interactive Widget Card -->
          <div class="lg:col-span-6">
            <div class="bg-white rounded-2xl p-6 sm:p-7 text-slate-900 shadow-2xl border border-slate-200">
              
              <!-- Store header mock -->
              <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-emerald-100 overflow-hidden">
                    <img src="https://picsum.photos/seed/nnekabeauty/100/100" alt="Nneka Glow" class="w-full h-full object-cover"/>
                  </div>
                  <div>
                    <div class="flex items-center gap-1">
                      <span class="font-bold text-ink text-sm">Nneka Glow Essentials</span>
                      <span class="text-brand">●</span>
                    </div>
                    <span class="text-xs text-slate-500">Ikeja, Lagos · 4.9 ★ (184 orders)</span>
                  </div>
                </div>
                <span class="text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-md">
                  Active Demo
                </span>
              </div>

              <!-- Product Item Selector in Widget -->
              <div class="space-y-4">
                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 flex items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <img src="https://picsum.photos/seed/skincareoil/120/120" alt="Turmeric Glow Oil" class="w-14 h-14 rounded-lg object-cover border border-slate-200"/>
                    <div>
                      <h4 class="font-bold text-ink text-sm">Radiance Glow Turmeric Body Oil</h4>
                      <p class="text-xs text-slate-500">100ml · For even tone & hyperpigmentation</p>
                      <span class="text-brand font-bold text-sm tabular-naira">₦8,500</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button id="demo-qty-minus" class="w-7 h-7 rounded-lg bg-white border border-slate-300 flex items-center justify-center text-slate-700 font-bold hover:bg-slate-100">-</button>
                    <span id="demo-qty" class="text-sm font-bold text-ink tabular-naira">1</span>
                    <button id="demo-qty-plus" class="w-7 h-7 rounded-lg bg-white border border-slate-300 flex items-center justify-center text-slate-700 font-bold hover:bg-slate-100">+</button>
                  </div>
                </div>

                <!-- Location Selector for Lagos -->
                <div class="grid grid-cols-2 gap-3 text-xs">
                  <div>
                    <label class="block font-medium text-slate-700 mb-1">Delivery Destination</label>
                    <select id="demo-lga" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-800 focus:outline-none focus:border-brand">
                      <option value="2000" selected>Lagos Island / Lekki (₦2,000)</option>
                      <option value="1500">Ikeja / Mainland (₦1,500)</option>
                      <option value="3500">Ikorodu / Epe (₦3,500)</option>
                      <option value="4500">Abuja / Interstate (₦4,500)</option>
                    </select>
                  </div>
                  <div>
                    <label class="block font-medium text-slate-700 mb-1">Payment Method</label>
                    <select id="demo-method" class="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-800 focus:outline-none focus:border-brand">
                      <option value="escrow">Protected Escrow (Recommended)</option>
                      <option value="direct">Direct Bank Transfer</option>
                      <option value="card">Debit Card (Paystack)</option>
                    </select>
                  </div>
                </div>

                <!-- Order Calculation -->
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs space-y-1.5">
                  <div class="flex justify-between text-slate-600">
                    <span>Item Subtotal:</span>
                    <span id="demo-subtotal" class="font-semibold text-slate-800 tabular-naira">₦8,500</span>
                  </div>
                  <div class="flex justify-between text-slate-600">
                    <span>Delivery Fee:</span>
                    <span id="demo-delivery" class="font-semibold text-slate-800 tabular-naira">₦2,000</span>
                  </div>
                  <div class="flex justify-between text-slate-900 font-bold text-sm pt-1.5 border-t border-slate-200">
                    <span>Total Amount:</span>
                    <span id="demo-total" class="text-brand tabular-naira">₦10,500</span>
                  </div>
                </div>

                <!-- Generated WhatsApp Message preview -->
                <div>
                  <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Generated WhatsApp Order Message
                  </label>
                  <div id="demo-message-preview" class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-[11px] text-emerald-950 font-mono whitespace-pre-wrap leading-relaxed">
Hello Nneka Glow! I would like to order:
• 1x Radiance Glow Turmeric Body Oil (₦8,500)
Delivery to: Lagos Island / Lekki (₦2,000)
Total: ₦10,500
Payment: Protected Escrow
Ref: #MP-DEMO77
                  </div>
                </div>

                <!-- CTA Button -->
                <button id="demo-simulate-btn" class="w-full bg-brand hover:bg-brand-dark text-white font-bold text-sm py-3 rounded-xl shadow transition-colors flex items-center justify-center gap-2">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <span>Simulate "Order via WhatsApp" Click</span>
                </button>

              </div>

            </div>
          </div>

        </div>

      </div>
    </section>

    <!-- TESTIMONIALS (NIGERIAN VENDORS) -->
    <section class="py-20 bg-surface border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <span class="text-brand font-semibold text-xs uppercase tracking-wider bg-brand-soft px-3 py-1 rounded-full">Real Vendor Stories</span>
          <h2 class="text-3xl sm:text-4xl font-display font-extrabold text-ink">
            Loved by 12,000+ businesses across Nigeria
          </h2>
          <p class="text-base text-body">
            From fashion designers in Surulere to shoe manufacturers in Aba, here is how mypage.ng solved chat burnout.
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          
          <!-- Testimonial 1 -->
          <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="space-y-4">
              <div class="flex items-center gap-1 text-amber-500 text-sm">
                ★★★★★
              </div>
              <p class="text-sm text-body leading-relaxed italic">
                "Before mypage.ng, I had 80 unread WhatsApp chats every evening with people asking 'how much?'. Now I just link my store in my status. People select their size, pay with Paystack, and I only pack parcels."
              </p>
            </div>
            <div class="pt-6 border-t border-slate-100 flex items-center gap-3 mt-6">
              <div class="w-11 h-11 rounded-full bg-slate-200 overflow-hidden">
                <img src="https://picsum.photos/seed/amaka/100/100" alt="Amaka Okafor" class="w-full h-full object-cover"/>
              </div>
              <div>
                <h4 class="font-bold text-ink text-sm">Amaka Okafor</h4>
                <p class="text-xs text-slate-500">Amaka Stitches · Lekki, Lagos</p>
                <span class="text-[10px] text-brand font-semibold">₦14.2M processed in 2026</span>
              </div>
            </div>
          </div>

          <!-- Testimonial 2 -->
          <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="space-y-4">
              <div class="flex items-center gap-1 text-amber-500 text-sm">
                ★★★★★
              </div>
              <p class="text-sm text-body leading-relaxed italic">
                "The escrow feature is a lifesaver. Customers in Abuja and Port Harcourt used to doubt sending money to Aba. Once they saw the escrow protection badge on mypage.ng, our interstate orders tripled."
              </p>
            </div>
            <div class="pt-6 border-t border-slate-100 flex items-center gap-3 mt-6">
              <div class="w-11 h-11 rounded-full bg-slate-200 overflow-hidden">
                <img src="https://picsum.photos/seed/chidi/100/100" alt="Chidi Kalu" class="w-full h-full object-cover"/>
              </div>
              <div>
                <h4 class="font-bold text-ink text-sm">Chidi Kalu</h4>
                <p class="text-xs text-slate-500">Aba Leather Hub · Ariaria, Abia State</p>
                <span class="text-[10px] text-brand font-semibold">Verified Merchant</span>
              </div>
            </div>
          </div>

          <!-- Testimonial 3 -->
          <div class="bg-white p-7 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="space-y-4">
              <div class="flex items-center gap-1 text-amber-500 text-sm">
                ★★★★★
              </div>
              <p class="text-sm text-body leading-relaxed italic">
                "My phone fell inside water at Balogun market and died. With my old way, I would have lost 300 customer contacts! But mypage saved everything in the customer book. Logged in on a new phone and my shop was alive."
              </p>
            </div>
            <div class="pt-6 border-t border-slate-100 flex items-center gap-3 mt-6">
              <div class="w-11 h-11 rounded-full bg-slate-200 overflow-hidden">
                <img src="https://picsum.photos/seed/funke/100/100" alt="Funke Adebayo" class="w-full h-full object-cover"/>
              </div>
              <div>
                <h4 class="font-bold text-ink text-sm">Funke Adebayo</h4>
                <p class="text-xs text-slate-500">Lagos Fresh Grills · Yaba, Lagos</p>
                <span class="text-[10px] text-brand font-semibold">4,200+ WhatsApp Customers</span>
              </div>
            </div>
          </div>

        </div>

      </div>
    </section>

    <!-- PRICING TEASER -->
    <section class="py-20 bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
          <span class="text-brand font-semibold text-xs uppercase tracking-wider bg-brand-soft px-3 py-1 rounded-full">Fair Nigerian Pricing</span>
          <h2 class="text-3xl sm:text-4xl font-display font-extrabold text-ink">
            Start for free. Upgrade when your volume booms.
          </h2>
          <p class="text-base text-body">
            No hidden setup fees. No card required to start. Pay monthly or save 20% with annual subscription.
          </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto items-stretch">
          
          <!-- Free Plan -->
          <div class="bg-surface rounded-2xl p-7 border border-slate-200 flex flex-col justify-between">
            <div class="space-y-5">
              <div>
                <span class="text-xs font-bold uppercase text-slate-500 tracking-wider">Free Starter</span>
                <h3 class="text-3xl font-display font-extrabold text-ink mt-1">₦0 <span class="text-sm font-normal text-slate-500">/ forever</span></h3>
                <p class="text-xs text-slate-600 mt-2">Perfect for side-hustlers and new WhatsApp vendors.</p>
              </div>

              <div class="border-t border-slate-200 pt-5 space-y-3 text-xs text-slate-700">
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> Up to 15 products</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> mypage.ng/yourname link</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> WhatsApp deep links</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> Order inbox (list view)</div>
                <div class="flex items-center gap-2 text-slate-400"><span>✕</span> Custom domain support</div>
                <div class="flex items-center gap-2 text-slate-400"><span>✕</span> Automated broadcasts</div>
              </div>
            </div>

            <div class="pt-6 mt-6 border-t border-slate-200">
              <a href="/register" class="w-full block text-center bg-white hover:bg-slate-100 text-slate-800 font-semibold text-sm py-3 rounded-xl border border-slate-300 transition-colors">
                Start Free
              </a>
            </div>
          </div>

          <!-- Pro Plan (Popular) -->
          <div class="bg-white rounded-2xl p-7 border-2 border-brand shadow-xl relative flex flex-col justify-between">
            <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-brand text-white font-bold text-xs uppercase tracking-wider px-3 py-1 rounded-full shadow-sm">
              Most Popular
            </div>

            <div class="space-y-5">
              <div>
                <span class="text-xs font-bold uppercase text-brand tracking-wider">Pro Merchant</span>
                <h3 class="text-3xl font-display font-extrabold text-ink mt-1 tabular-naira">₦3,500 <span class="text-sm font-normal text-slate-500">/ month</span></h3>
                <p class="text-xs text-slate-600 mt-2">For growing brands needing full automation & unlimited products.</p>
              </div>

              <div class="border-t border-slate-100 pt-5 space-y-3 text-xs text-slate-700">
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> <strong>Unlimited</strong> products & variants</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> <strong>Order Kanban pipeline</strong></div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> Customer Book & LTV tracking</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> WhatsApp restock broadcasts</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> Same-day bank settlements</div>
                <div class="flex items-center gap-2"><span class="text-brand font-bold">✓</span> Verified merchant green tick</div>
              </div>
            </div>

            <div class="pt-6 mt-6 border-t border-slate-100">
              <a href="#" class="w-full block text-center bg-brand hover:bg-brand-dark text-white font-bold text-sm py-3.5 rounded-xl shadow-wa transition-colors">
                Upgrade to Pro (₦3,500)
              </a>
            </div>
          </div>

          <!-- Pro + Escrow Plan -->
          <div class="bg-surface rounded-2xl p-7 border border-slate-200 flex flex-col justify-between">
            <div class="space-y-5">
              <div>
                <span class="text-xs font-bold uppercase text-accent tracking-wider">Pro + Escrow</span>
                <h3 class="text-3xl font-display font-extrabold text-ink mt-1 tabular-naira">₦7,000 <span class="text-sm font-normal text-slate-500">/ month</span></h3>
                <p class="text-xs text-slate-600 mt-2">High-ticket items (phones, hair, jewelry) needing buyer trust guarantee.</p>
              </div>

              <div class="border-t border-slate-200 pt-5 space-y-3 text-xs text-slate-700">
                <div class="flex items-center gap-2"><span class="text-accent font-bold">✓</span> Everything in Pro</div>
                <div class="flex items-center gap-2"><span class="text-accent font-bold">✓</span> <strong>Protected Escrow Vault</strong></div>
                <div class="flex items-center gap-2"><span class="text-accent font-bold">✓</span> Dedicated dispute manager</div>
                <div class="flex items-center gap-2"><span class="text-accent font-bold">✓</span> Custom domain (yourbrand.com.ng)</div>
                <div class="flex items-center gap-2"><span class="text-accent font-bold">✓</span> Team accounts (up to 5 staff)</div>
              </div>
            </div>

            <div class="pt-6 mt-6 border-t border-slate-200">
              <a href="#" class="w-full block text-center bg-white hover:bg-slate-100 text-slate-800 font-semibold text-sm py-3 rounded-xl border border-slate-300 transition-colors">
                Compare All Plans
              </a>
            </div>
          </div>

        </div>

      </div>
    </section>

    <!-- FAQ SECTION -->
    <section id="faq" class="py-20 bg-surface border-b border-slate-200">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-14 space-y-3">
          <span class="text-brand font-semibold text-xs uppercase tracking-wider bg-brand-soft px-3 py-1 rounded-full">Got Questions?</span>
          <h2 class="text-3xl font-display font-extrabold text-ink">
            Frequently Asked Questions
          </h2>
          <p class="text-sm text-body">Everything you need to know about setting up and running your shop.</p>
        </div>

        <!-- Accordion (functional with vanilla JS) -->
        <div class="space-y-3" id="faq-accordion">
          
          <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <button class="w-full p-5 text-left font-display font-bold text-ink text-sm sm:text-base flex items-center justify-between gap-4 faq-toggle focus:outline-none">
              <span>Do my customers need to download an app or register?</span>
              <span class="text-brand font-bold text-lg faq-icon">+</span>
            </button>
            <div class="px-5 pb-5 text-xs sm:text-sm text-body leading-relaxed hidden faq-content">
              No! That is the beauty of mypage.ng. When you share your link, it opens instantly in any browser. Customers can browse your catalog, select variants, and tap "Order on WhatsApp" or pay immediately via Paystack/Flutterwave without creating any passwords.
            </div>
          </div>

          <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <button class="w-full p-5 text-left font-display font-bold text-ink text-sm sm:text-base flex items-center justify-between gap-4 faq-toggle focus:outline-none">
              <span>How do I get my money when a customer pays?</span>
              <span class="text-brand font-bold text-lg faq-icon">+</span>
            </button>
            <div class="px-5 pb-5 text-xs sm:text-sm text-body leading-relaxed hidden faq-content">
              You can connect any Nigerian bank account (Access, GTBank, Zenith, Opay, PalmPay, Kuda, Moniepoint). Payouts are processed same-day or automatically at 6:00 PM Nigerian time into your linked account.
            </div>
          </div>

          <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <button class="w-full p-5 text-left font-display font-bold text-ink text-sm sm:text-base flex items-center justify-between gap-4 faq-toggle focus:outline-none">
              <span>How does Escrow Protection work for interstate deliveries?</span>
              <span class="text-brand font-bold text-lg faq-icon">+</span>
            </button>
            <div class="px-5 pb-5 text-xs sm:text-sm text-body leading-relaxed hidden faq-content">
              When an interstate buyer enables escrow, their payment is locked in our secure escrow vault. You receive an alert verifying funds are 100% held. Once the buyer confirms delivery (or 72 hours after logistics tracking verifies delivery), the funds are released straight to your wallet.
            </div>
          </div>

          <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <button class="w-full p-5 text-left font-display font-bold text-ink text-sm sm:text-base flex items-center justify-between gap-4 faq-toggle focus:outline-none">
              <span>Can I use mypage.ng on an Android phone or iPhone?</span>
              <span class="text-brand font-bold text-lg faq-icon">+</span>
            </button>
            <div class="px-5 pb-5 text-xs sm:text-sm text-body leading-relaxed hidden faq-content">
              Yes, 100%. The entire dashboard, product management, and order kanban are mobile-first and optimized for phone screens. You never need a laptop to run your business.
            </div>
          </div>

          <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <button class="w-full p-5 text-left font-display font-bold text-ink text-sm sm:text-base flex items-center justify-between gap-4 faq-toggle focus:outline-none">
              <span>Will WhatsApp ban my phone number for sending broadcasts?</span>
              <span class="text-brand font-bold text-lg faq-icon">+</span>
            </button>
            <div class="px-5 pb-5 text-xs sm:text-sm text-body leading-relaxed hidden faq-content">
              No. Unlike unauthorized spam bots, our broadcast system integrates with Meta's official WhatsApp Business Cloud API. Messages are sent via approved templates adhering strictly to WhatsApp's October 2026 guidelines.
            </div>
          </div>

        </div>

      </div>
    </section>

    <!-- FINAL CALL TO ACTION -->
    <section class="py-20 bg-ink text-white text-center relative overflow-hidden">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 space-y-6">
        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-400 bg-emerald-950 px-3 py-1 rounded-full border border-emerald-800">
          Ready to stop chat stress?
        </span>
        <h2 class="text-3xl sm:text-5xl font-display font-extrabold text-white leading-tight">
          Claim your free mypage.ng link in 60 seconds.
        </h2>
        <p class="text-slate-300 text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
          Join 12,000+ Nigerian sellers today. Setup is free, instant, and works directly on WhatsApp.
        </p>

        <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
          <a href="/register" class="w-full sm:w-auto bg-brand hover:bg-brand-dark text-white font-semibold text-base px-8 py-4 rounded-xl transition-all shadow-wa">
            Create Your Free Shop Now →
          </a>
          <a href="#" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-base px-6 py-4 rounded-xl border border-slate-700 transition-all">
            Explore Verified Sellers
          </a>
        </div>
        <p class="text-xs text-slate-400 pt-2">No credit card or BVN required to register · Cancel anytime</p>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-white border-t border-slate-200 pt-16 pb-12 text-slate-600 text-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="grid grid-cols-2 md:grid-cols-5 gap-8 mb-12">
          
          <div class="col-span-2 space-y-4">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center text-white">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
              </div>
              <span class="font-display font-bold text-lg text-ink">mypage<span class="text-brand">.ng</span></span>
            </div>
            <p class="text-body max-w-sm leading-relaxed">
              WhatsApp-first commerce infrastructure for Nigerian small businesses. Built with love in Lagos, Nigeria.
            </p>
            <div class="flex items-center gap-2 text-slate-500 font-medium">
              <span>🇳🇬 Lagos</span>
              <span>·</span>
              <span>Abuja</span>
              <span>·</span>
              <span>Aba</span>
              <span>·</span>
              <span>Port Harcourt</span>
            </div>
          </div>

          <div>
            <h4 class="font-bold text-ink mb-3 uppercase tracking-wider text-[11px]">Product</h4>
            <ul class="space-y-2">
              <li><a href="#" class="hover:text-brand">Page Builder</a></li>
              <li><a href="#" class="hover:text-brand">Order Kanban</a></li>
              <li><a href="#" class="hover:text-brand">Escrow Protection</a></li>
              <li><a href="#" class="hover:text-brand">Broadcasts</a></li>
              <li><a href="#" class="hover:text-brand">Inventory Manager</a></li>
            </ul>
          </div>

          <div>
            <h4 class="font-bold text-ink mb-3 uppercase tracking-wider text-[11px]">Directory</h4>
            <ul class="space-y-2">
              <li><a href="#" class="hover:text-brand">Verified Fashion Sellers</a></li>
              <li><a href="#" class="hover:text-brand">Beauty & Skincare</a></li>
              <li><a href="#" class="hover:text-brand">Gadgets & Phones</a></li>
              <li><a href="#" class="hover:text-brand">Aba Leather Artisans</a></li>
              <li><a href="#" class="hover:text-brand">Food & Pastries</a></li>
            </ul>
          </div>

          <div>
            <h4 class="font-bold text-ink mb-3 uppercase tracking-wider text-[11px]">Portal & Admin</h4>
            <ul class="space-y-2">
              <li><a href="/dashboard" class="hover:text-brand">Seller Dashboard</a></li>
              <li><a href="/login" class="hover:text-brand">Seller Login</a></li>
              <li><a href="/register" class="hover:text-brand">Open a Store</a></li>
              <li><a href="#" class="hover:text-brand">Pro Pricing</a></li>
              <li><a href="#" class="hover:text-brand text-slate-400">Admin Control Panel</a></li>
            </ul>
          </div>

        </div>

        <div class="pt-8 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 text-slate-500">
          <p>© 2026 mypage.ng Technologies Limited. All rights reserved. RC: 1849204.</p>
          <div class="flex items-center gap-6">
            <span class="flex items-center gap-1.5 text-slate-700 font-medium">
              <svg class="w-4 h-4 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              NDPR Compliant
            </span>
            <span class="flex items-center gap-1.5 text-slate-700 font-medium">
              <span class="w-2 h-2 rounded-full bg-brand"></span>
              CBN Compliant Escrow Partner
            </span>
          </div>
        </div>

      </div>
    </footer>

    <!-- Floating Site Map Drawer Trigger Button -->
    <div class="fixed bottom-6 left-6 z-50">
      <button id="sitemap-toggle-btn" class="bg-ink hover:bg-slate-800 text-white font-semibold text-xs px-3.5 py-2.5 rounded-full shadow-2xl flex items-center gap-2 border border-slate-700 hover:scale-105 transition-all">
        <span class="w-2 h-2 rounded-full bg-brand animate-pulse"></span>
        <span>Site Map · 28 Pages</span>
        <span class="bg-slate-800 text-slate-300 px-1.5 py-0.5 rounded text-[10px] border border-slate-700">Explore</span>
      </button>
    </div>

    <!-- Site Map Drawer Modal -->
    <div id="sitemap-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
      <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-surface">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-brand text-white flex items-center justify-center font-bold">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
            </div>
            <div>
              <h3 class="font-display font-extrabold text-base text-ink">mypage.ng Complete UI Architecture</h3>
              <p class="text-xs text-slate-500">28 Production-Grade Self-Contained HTML Pages</p>
            </div>
          </div>
          <button id="sitemap-close-btn" class="p-2 text-slate-400 hover:text-ink hover:bg-slate-100 rounded-lg">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="p-6 overflow-y-auto space-y-6 text-xs">
          <!-- Public Pages -->
          <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Public & Marketing
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
              <a href="/" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Landing Page</div><div class="text-[11px] text-slate-500">index.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Pricing & Plans</div><div class="text-[11px] text-slate-500">pricing.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Seller Directory</div><div class="text-[11px] text-slate-500">directory.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="/login" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Seller Login</div><div class="text-[11px] text-slate-500">login.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="/register" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Open Free Store</div><div class="text-[11px] text-slate-500">register.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="/onboarding/business" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">3-Step Onboarding</div><div class="text-[11px] text-slate-500">onboarding.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
            </div>
          </div>

          <!-- Seller Dashboard Suite -->
          <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Seller Dashboard Suite
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
              <a href="/dashboard" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Dashboard Overview</div><div class="text-[11px] text-slate-500">dashboard.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Store Page Builder</div><div class="text-[11px] text-slate-500">page-builder.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Products & Catalog</div><div class="text-[11px] text-slate-500">products.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Add / Edit Product</div><div class="text-[11px] text-slate-500">product-form.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Orders Kanban Pipeline</div><div class="text-[11px] text-slate-500">orders.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Order Detail & Chat</div><div class="text-[11px] text-slate-500">order-detail.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Customer CRM Book</div><div class="text-[11px] text-slate-500">customers.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Customer Profile & LTV</div><div class="text-[11px] text-slate-500">customer-detail.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Inventory Manager</div><div class="text-[11px] text-slate-500">inventory.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Payments & Payouts</div><div class="text-[11px] text-slate-500">payments.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Escrow Vault</div><div class="text-[11px] text-slate-500">escrow.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">WhatsApp Broadcasts</div><div class="text-[11px] text-slate-500">messaging.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Analytics & Charts</div><div class="text-[11px] text-slate-500">analytics.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Billing & Invoices</div><div class="text-[11px] text-slate-500">billing.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Store Settings</div><div class="text-[11px] text-slate-500">settings.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
            </div>
          </div>

          <!-- Buyer Experience -->
          <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span> Buyer Experience (Amaka Stitches Store)
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Live Storefront</div><div class="text-[11px] text-slate-500">store.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Protected Checkout</div><div class="text-[11px] text-slate-500">checkout.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Order Tracking</div><div class="text-[11px] text-slate-500">order-tracking.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
            </div>
          </div>

          <!-- Platform Ops Console -->
          <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> Platform Ops & Admin Console
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2">
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Admin Dashboard</div><div class="text-[11px] text-slate-500">admin-dashboard.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Seller Moderation</div><div class="text-[11px] text-slate-500">admin-sellers.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Dispute Mediation</div><div class="text-[11px] text-slate-500">admin-disputes.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
              <a href="#" class="p-3 rounded-xl border border-slate-200 hover:border-brand hover:bg-brand-50 transition-colors flex items-center justify-between">
                <div><div class="font-bold text-ink">Transactions Audit</div><div class="text-[11px] text-slate-500">admin-transactions.html</div></div>
                <span class="text-brand font-bold text-sm">→</span>
              </a>
            </div>
          </div>
        </div>

        <div class="p-4 bg-surface border-t border-slate-200 flex items-center justify-between">
          <span class="text-[11px] text-slate-500">All 28 files self-contained, standalone vanilla JS & Tailwind CDN.</span>
          <button onclick="document.getElementById('sitemap-modal').classList.add('hidden')" class="px-4 py-1.5 rounded-lg bg-ink text-white font-semibold text-xs hover:bg-slate-800">Close</button>
        </div>
      </div>
    </div>

    <!-- Interactive Toast Notification Element -->
    <div id="toast" class="fixed bottom-6 right-6 z-50 transform translate-y-24 opacity-0 transition-all duration-300 pointer-events-none bg-slate-900 text-white px-4 py-3 rounded-xl shadow-xl flex items-center gap-3 border border-slate-700 max-w-sm">
      <div id="toast-icon" class="w-6 h-6 rounded-full bg-brand text-white flex items-center justify-center text-xs font-bold shrink-0">✓</div>
      <p id="toast-message" class="text-xs font-medium leading-tight">Action completed successfully</p>
    </div>

    <!-- VANILLA JAVASCRIPT LOGIC -->
    <script>
      // Demo State
      const DEMO = {
        storeName: "Amaka Stitches",
        phone: "+234 803 456 7890",
        product: {
          name: "Radiance Glow Turmeric Body Oil",
          price: 8500,
          qty: 1
        },
        deliveryFee: 2000,
        lgaName: "Lagos Island / Lekki",
        method: "Protected Escrow"
      };

      // Toast notification helper
      function showToast(message, icon = "✓", bg = "bg-brand") {
        const toast = document.getElementById('toast');
        const toastMsg = document.getElementById('toast-message');
        const toastIcon = document.getElementById('toast-icon');
        
        toastMsg.textContent = message;
        toastIcon.textContent = icon;
        toastIcon.className = `w-6 h-6 rounded-full ${bg} text-white flex items-center justify-center text-xs font-bold shrink-0`;
        
        toast.classList.remove('translate-y-24', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-y-0', 'opacity-100');

        setTimeout(() => {
          toast.classList.add('translate-y-24', 'opacity-0', 'pointer-events-none');
          toast.classList.remove('translate-y-0', 'opacity-100');
        }, 3200);
      }

      // Format currency
      function formatNaira(amount) {
        return '₦' + Number(amount).toLocaleString('en-NG');
      }

      // Update Demo Calculation & Message preview
      function updateDemoCalculation() {
        const qtyEl = document.getElementById('demo-qty');
        const subtotalEl = document.getElementById('demo-subtotal');
        const deliveryEl = document.getElementById('demo-delivery');
        const totalEl = document.getElementById('demo-total');
        const previewEl = document.getElementById('demo-message-preview');
        const lgaSelect = document.getElementById('demo-lga');
        const methodSelect = document.getElementById('demo-method');

        const qty = DEMO.product.qty;
        const subtotal = DEMO.product.price * qty;
        const delivery = parseInt(lgaSelect.value, 10);
        const total = subtotal + delivery;
        const selectedLgaText = lgaSelect.options[lgaSelect.selectedIndex].text.split('(')[0].trim();
        const selectedMethodText = methodSelect.options[methodSelect.selectedIndex].text;

        qtyEl.textContent = qty;
        subtotalEl.textContent = formatNaira(subtotal);
        deliveryEl.textContent = formatNaira(delivery);
        totalEl.textContent = formatNaira(total);

        // Update prefilled preview
        previewEl.textContent = 
`Hello Nneka Glow! I would like to order:
• ${qty}x ${DEMO.product.name} (${formatNaira(subtotal)})
Delivery to: ${selectedLgaText} (${formatNaira(delivery)})
Total: ${formatNaira(total)}
Payment: ${selectedMethodText}
Ref: #MP-DEMO77`;
      }

      // Event listeners on load
      document.addEventListener('DOMContentLoaded', () => {
        // Mobile navigation toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileNav = document.getElementById('mobile-nav');
        if (mobileMenuBtn && mobileNav) {
          mobileMenuBtn.addEventListener('click', () => {
            mobileNav.classList.toggle('hidden');
          });
        }

        // Store claim form
        const claimForm = document.getElementById('claim-store-form');
        const handleInput = document.getElementById('store-handle-input');
        if (claimForm && handleInput) {
          claimForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const val = handleInput.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
            if (val) {
              showToast(`Checking availability for mypage.ng/${val}...`, "⏳", "bg-amber-500");
              setTimeout(() => {
                showToast(`Great news! mypage.ng/${val} is available! 🎉`, "✓", "bg-brand");
                setTimeout(() => {
                  window.location.href = `/onboarding/business?store=${encodeURIComponent(val)}`;
                }, 1200);
              }, 600);
            }
          });
        }

        // Quantity controls
        const plusBtn = document.getElementById('demo-qty-plus');
        const minusBtn = document.getElementById('demo-qty-minus');
        const lgaSelect = document.getElementById('demo-lga');
        const methodSelect = document.getElementById('demo-method');

        if (plusBtn) {
          plusBtn.addEventListener('click', () => {
            DEMO.product.qty++;
            updateDemoCalculation();
          });
        }

        if (minusBtn) {
          minusBtn.addEventListener('click', () => {
            if (DEMO.product.qty > 1) {
              DEMO.product.qty--;
              updateDemoCalculation();
            }
          });
        }

        if (lgaSelect) {
          lgaSelect.addEventListener('change', updateDemoCalculation);
        }

        if (methodSelect) {
          methodSelect.addEventListener('change', updateDemoCalculation);
        }

        // Simulate WhatsApp order button
        const simBtn = document.getElementById('demo-simulate-btn');
        if (simBtn) {
          simBtn.addEventListener('click', () => {
            const previewEl = document.getElementById('demo-message-preview');
            showToast("WhatsApp deep-link generated with prefilled cart! Redirecting to checkout...", "📱", "bg-brand");
            setTimeout(() => {
              window.location.href = "#";
            }, 1000);
          });
        }

        // FAQ Accordion
        const faqToggles = document.querySelectorAll('.faq-toggle');
        faqToggles.forEach(toggle => {
          toggle.addEventListener('click', () => {
            const content = toggle.nextElementSibling;
            const icon = toggle.querySelector('.faq-icon');
            const isHidden = content.classList.contains('hidden');
            
            // Close all first
            document.querySelectorAll('.faq-content').forEach(c => c.classList.add('hidden'));
            document.querySelectorAll('.faq-icon').forEach(i => i.textContent = '+');

            if (isHidden) {
              content.classList.remove('hidden');
              icon.textContent = '−';
            }
          });
        });

        // Site Map Modal toggle
        const sitemapBtn = document.getElementById('sitemap-toggle-btn');
        const sitemapCloseBtn = document.getElementById('sitemap-close-btn');
        const sitemapModal = document.getElementById('sitemap-modal');

        if (sitemapBtn && sitemapModal) {
          sitemapBtn.addEventListener('click', () => {
            sitemapModal.classList.remove('hidden');
          });
        }
        if (sitemapCloseBtn && sitemapModal) {
          sitemapCloseBtn.addEventListener('click', () => {
            sitemapModal.classList.add('hidden');
          });
        }
      });
    </script>
    </body>
</html>
