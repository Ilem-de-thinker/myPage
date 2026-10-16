@props(['page', 'service'])

@php
    /*
      One service, in full.

      The design has no single-service page of its own, so this is built from the
      two pieces it does have: the service card's anatomy, unclamped, on the left;
      the booking summary panel from the booking page, bound to this service
      rather than to a selection, on the right. Same card language as the grid
      the buyer just came from, so moving between them changes the information
      and not the design.
    */
    $deliverables = $service->deliverableList();
    $where = $page->locationLabel();
    $deposit = $service->depositKobo();
    $balance = max(0, $service->price_kobo - $deposit);
    $bookUrl = route('storefront.book', ['slug' => $page->slug, 'service' => $service->id]);
@endphp

<section class="section-y border-b border-[var(--rule)]">
    <div class="shell">

        {{-- Breadcrumb: the grid this was reached from. --}}
        <nav class="mb-8 text-xs text-[var(--ink-3)]" aria-label="Breadcrumb">
            <a href="{{ route('storefront.show', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)] transition-colors">{{ $page->name }}</a>
            <span aria-hidden="true"> / </span>
            <a href="{{ route('storefront.services', ['slug' => $page->slug]) }}" class="hover:text-[var(--ink)] transition-colors">Services</a>
            <span aria-hidden="true"> / </span>
            <span class="text-[var(--ink)]">{{ $service->name }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8">

            {{-- Left: the service itself (8 Columns) --}}
            <div class="lg:col-span-8">
                <article class="card p-4 sm:p-6">

                    {{-- The seller's own photograph. No image is not a hole: the
                         plate keeps its surface wash and carries the initial. --}}
                    <div class="plate aspect-[16/9] mb-6">
                        <div class="plate-media h-full">
                            @if ($service->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($service->image_path) }}"
                                     alt="{{ $service->name }}"
                                     class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full w-full items-center justify-center font-[family-name:var(--font-heading)] text-6xl font-extrabold"
                                      style="color: var(--brand-text)" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($service->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="grain" aria-hidden="true"></div>
                    </div>

                    <div class="flex items-center justify-between gap-2 mb-1">
                        <p class="t-meta">{{ $service->turnaround_label ?: $service->billingUnit()->label() }}</p>
                        <span class="text-xs text-[var(--ink-3)] font-medium">{{ $where ?: '—' }}</span>
                    </div>

                    <h1 class="t-display-2 text-[var(--ink)] mb-4">{{ $service->name }}</h1>

                    @if ($service->description)
                        <p class="t-body text-[var(--ink-2)] leading-relaxed mb-6">{{ $service->description }}</p>
                    @endif

                    @if ($deliverables !== [])
                        <div class="mb-6">
                            <h2 class="t-title text-[var(--ink)] mb-3">What is included</h2>
                            <ul class="text-sm text-[var(--ink-2)] space-y-1.5">
                                @foreach ($deliverables as $deliverable)
                                    <li class="flex items-start gap-2">
                                        <span class="w-1 h-1 rounded-full bg-[var(--brand)] mt-2 shrink-0" aria-hidden="true"></span>
                                        <span>{{ $deliverable }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4 text-sm py-4 border-y border-[var(--rule)]">
                        <div>
                            <span class="t-meta block">Duration</span>
                            <span class="font-semibold text-[var(--ink)] mt-0.5 block">{{ $service->durationLabel() }}</span>
                        </div>
                        <div>
                            <span class="t-meta block">Deposit Required</span>
                            <span class="font-semibold text-[var(--brand-text)] mt-0.5 block">
                                @if ($service->hasDeposit())
                                    {{ \App\Shared\Support\Money::format($deposit) }} (Escrow)
                                @else
                                    None — paid in full
                                @endif
                            </span>
                        </div>
                    </div>

                    @if ($page->city || $page->state)
                        <p class="t-meta mt-4">
                            {{ collect([$page->city, $page->state])->filter()->join(', ') }} · {{ $page->settings['timezone'] ?? config('mypage.timezone') }}
                        </p>
                    @endif
                </article>
            </div>

            {{-- Right: the booking summary panel from the booking page (4 Columns) --}}
            <div class="lg:col-span-4 lg:sticky lg:top-24 space-y-6 self-start">
                <div class="card p-6">
                    <h2 class="text-sm font-bold text-[var(--ink)] pb-3 border-b border-[var(--rule)]">Booking Summary</h2>

                    <div class="space-y-3.5 my-4 text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[var(--ink-3)]">Service Package</span>
                            <span class="font-semibold text-[var(--ink)] text-right">{{ $service->name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--ink-3)]">Duration</span>
                            <span class="font-semibold text-[var(--ink)]">{{ $service->durationLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--ink-3)]">Location</span>
                            <span class="font-semibold text-[var(--ink)] text-right">{{ $where ?: '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-[var(--rule)]">
                            <span class="font-semibold text-[var(--ink)]">{{ $service->hasDeposit() ? 'Deposit Due Now' : 'Total Due Now' }}</span>
                            <span class="t-price-sm">{{ $service->hasDeposit() ? \App\Shared\Support\Money::format($deposit) : $service->priceLabel() }}</span>
                        </div>
                        @if ($service->hasDeposit() && $balance > 0)
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-[var(--ink-3)]">Balance on the day</span>
                                <span class="font-semibold text-[var(--ink-2)]">{{ \App\Shared\Support\Money::format($balance) }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Escrow Guarantee Badge --}}
                    <div class="p-3.5 rounded-[var(--radius)] bg-[var(--surface)] border border-[var(--rule)] flex items-start gap-2.5 text-xs text-[var(--ink-2)]">
                        <svg class="w-4 h-4 text-[var(--brand)] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span>100% Escrow Protection: deposit held securely until the session is completed.</span>
                    </div>

                    <p class="t-meta text-center mt-4">
                        Protected by the mypage.ng verified sovereign booking protocol.
                    </p>

                    <a href="{{ $bookUrl }}"
                       class="relative z-20 mt-5 w-full h-12 rounded-[var(--radius)] bg-[var(--brand)] text-[var(--brand-ink)] font-bold text-sm flex items-center justify-center hover:opacity-90 transition-opacity">
                        Reserve a slot
                    </a>

                    @if ($page->whatsapp_number)
                        <a href="{{ \App\Shared\Support\WhatsApp::buyerLink($page->whatsapp_number, 'Hello, I would like to ask about '.$service->name) }}"
                           target="_blank" rel="noopener"
                           class="mt-2 w-full h-11 rounded-[var(--radius)] bg-[var(--card)] text-[var(--ink)] border border-[var(--rule)] font-semibold text-xs flex items-center justify-center hover:border-[var(--brand-edge)] transition-colors">
                            Ask a question first
                        </a>
                    @endif
                </div>
            </div>

        </div>
    </div>
</section>