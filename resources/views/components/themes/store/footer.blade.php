@props(['page'])

@php
    use App\Shared\Support\StoreUrl;
    use App\Shared\Support\SocialLinks;
    use App\Shared\Support\WhatsApp;

    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::homePath($page);
    $wa = WhatsApp::link($page, 'Hello '.$page->name.', I am browsing your storefront.');
    $themePreset = $page->settings['theme_preset'] ?? 'catalog';

    // The design lists named collections under the wordmark. A seller with three
    // categories and a seller with none both have to render, so this is derived
    // from the catalogue rather than written into the template — and only
    // categories that still have something in them are listed, because a link to
    // an empty shelf is worse than no link at all.
    $categories = $page->categories()
        ->whereHas('products', fn ($query) => $query->active())
        ->limit(5)
        ->get();
    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');

    // Only networks the seller actually filled in render a link.
    $social = SocialLinks::forPage($page);
@endphp

<footer data-footer-style="{{ $themePreset }}" class="theme-footer border-t border-[var(--rule)] bg-[var(--surface)] text-xs text-[var(--ink-2)] py-14">
    <div class="shell">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
            <div class="space-y-3">
                <a href="{{ $home }}" class="flex items-center gap-1.5 text-base font-extrabold tracking-tight text-[var(--ink)]">
                    @if ($page->logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}" alt="" width="28" height="28" class="w-7 h-7 rounded-[var(--radius-sm)] object-cover">
                    @else
                        <span class="w-2.5 h-2.5 rounded-full bg-[var(--brand)]" aria-hidden="true"></span>
                    @endif
                    <span style="font-family: var(--font-heading)">{{ $page->name }}</span>
                </a>
                @if ($page->bio)
                    <p class="t-body text-xs text-[var(--ink-3)] leading-relaxed t-measure-narrow">{{ $page->bio }}</p>
                @endif
                @if ($where)
                    <p class="t-meta">{{ $where }}</p>
                @endif

                {{-- Only the address a seller chose to publish. --}}
                @if ($page->public_email)
                    <p class="t-meta">
                        <a href="mailto:{{ $page->public_email }}" class="hover:text-[var(--ink)] transition-colors">{{ $page->public_email }}</a>
                    </p>
                @endif

                @if ($social !== [])
                    <p class="t-meta">
                        @foreach ($social as $link)
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow" class="hover:text-[var(--ink)] transition-colors">{{ $link['label'] }}</a>@if (! $loop->last)<span aria-hidden="true"> · </span>@endif
                        @endforeach
                    </p>
                @endif
            </div>

            <div>
                <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Store Catalogue</h4>
                <ul class="space-y-2 text-xs">
                    @forelse ($categories as $category)
                        <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', array_merge($storefront, ['category' => $category->slug])) }}" class="hover:text-[var(--ink)] transition-colors">{{ $category->name }}</a></li>
                    @empty
                        <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.products', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Browse everything</a></li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Customer Service &amp; Payments</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.track-order', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Track Waybill Reference</a></li>
                    @if ($page->bio)
                        <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.about', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Our Story</a></li>
                    @endif
                    <li><a href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Studio Appointments &amp; Sizing</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Buyer Account Portal</h4>
                <p class="text-xs text-[var(--ink-3)] leading-relaxed mb-3">
                    Buyers never need a password. Access your order history and dispatch updates with your WhatsApp phone number.
                </p>
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.track-order', $storefront) }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-xs font-bold text-[var(--ink)] hover:border-[var(--brand-edge)] transition-colors">
                    <svg class="w-3.5 h-3.5 text-[var(--brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Buyer Order Sign-In</span>
                </a>
            </div>
        </div>

        <div class="pt-8 border-t border-[var(--rule)] flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-[var(--ink-3)]">
            <p>© {{ now()->year }} {{ $page->name }}. Powered by <a href="{{ \App\Shared\Support\StoreUrl::platform('home') }}" class="underline hover:text-[var(--ink)]">mypage.ng</a> sovereign infrastructure.</p>
            <div class="flex items-center gap-6">
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.about', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">About Us</a>
                <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.contact', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Contact</a>

                {{-- Buyers never need an account, so this is the only sign-in door
                     on the page. Once the seller is signed in it is their shop. --}}
                <a href="{{ auth()->check() ? \App\Shared\Support\StoreUrl::platform('dashboard') : \App\Shared\Support\StoreUrl::platform('login') }}"
                   class="hover:text-[var(--ink)] transition-colors">{{ auth()->check() ? 'Manage your page' : 'Sell on mypage.ng' }}</a>
            </div>
        </div>

        <nav aria-label="Legal" class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-[var(--ink-3)]">
            <a href="{{ \App\Shared\Support\StoreUrl::platform('legal.policy', ['policy' => 'privacy']) }}" class="hover:text-[var(--ink)]">Privacy</a>
            <a href="{{ \App\Shared\Support\StoreUrl::platform('legal.policy', ['policy' => 'terms']) }}" class="hover:text-[var(--ink)]">Terms</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.policy', array_merge($storefront, ['policy' => 'refunds'])) }}" class="hover:text-[var(--ink)]">Store refunds</a>
            <a href="{{ \App\Shared\Support\StoreUrl::path('storefront.policy', array_merge($storefront, ['policy' => 'delivery'])) }}" class="hover:text-[var(--ink)]">Delivery</a>
            <a href="{{ \App\Shared\Support\StoreUrl::platform('legal.policy', ['policy' => 'contact']) }}" class="hover:text-[var(--ink)]">Platform contact</a>
        </nav>

        <details class="mt-8 border-t border-[var(--rule)] pt-6">
            <summary class="cursor-pointer text-xs font-semibold text-[var(--ink-2)]">Report a problem with this store</summary>
            <form method="POST" action="{{ \App\Shared\Support\StoreUrl::path('storefront.report', $storefront) }}" class="mt-4 grid gap-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] p-4 md:grid-cols-2">
                @csrf
                <label class="text-xs font-semibold text-[var(--ink-2)]">What happened?
                    <select name="category" required class="mt-1 min-h-11 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] px-3 text-xs text-[var(--ink)]">
                        <option value="fraud">Fraud or scam</option>
                        <option value="not_delivered">Goods or service not delivered</option>
                        <option value="not_as_described">Not as described</option>
                        <option value="misleading_listing">Misleading listing</option>
                        <option value="payment_problem">Payment problem</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-[var(--ink-2)]">Your contact (optional)
                    <input name="reporter_contact" maxlength="190" class="mt-1 min-h-11 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] px-3 text-xs text-[var(--ink)]">
                </label>
                <label class="text-xs font-semibold text-[var(--ink-2)] md:col-span-2">Details
                    <textarea name="body" required minlength="20" maxlength="2000" rows="3" class="mt-1 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] p-3 text-xs text-[var(--ink)]" placeholder="Tell mypage.ng what happened (at least 20 characters)."></textarea>
                </label>
                <button class="w-fit rounded-[var(--radius-sm)] bg-[var(--brand)] px-4 py-2 text-xs font-bold text-[var(--brand-ink)]">Send report</button>
            </form>
        </details>

        <details class="mt-6 border-t border-[var(--rule)] pt-6">
            <summary class="cursor-pointer text-xs font-semibold text-[var(--ink-2)]">Contact this store</summary>
            <form method="POST" action="{{ \App\Shared\Support\StoreUrl::path('storefront.support', $storefront) }}" class="mt-4 grid gap-3 rounded-[var(--radius)] border border-[var(--rule)] bg-[var(--paper)] p-4 md:grid-cols-2">
                @csrf
                <input type="hidden" name="destination" value="store">
                <label class="text-xs font-semibold text-[var(--ink-2)]">Topic
                    <select name="category" required class="mt-1 min-h-11 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] px-3 text-xs text-[var(--ink)]">
                        <option value="question">General question</option>
                        <option value="payment">Payment</option>
                        <option value="order">Order</option>
                        <option value="technical">Technical problem</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-[var(--ink-2)]">Your name (optional)
                    <input name="requester_name" maxlength="120" class="mt-1 min-h-11 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] px-3 text-xs text-[var(--ink)]">
                </label>
                <label class="text-xs font-semibold text-[var(--ink-2)]">Your contact (optional)
                    <input name="requester_contact" maxlength="190" class="mt-1 min-h-11 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] px-3 text-xs text-[var(--ink)]">
                </label>
                <label class="text-xs font-semibold text-[var(--ink-2)]">Subject
                    <input name="subject" maxlength="180" required class="mt-1 min-h-11 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] px-3 text-xs text-[var(--ink)]">
                </label>
                <label class="text-xs font-semibold text-[var(--ink-2)] md:col-span-2">Message
                    <textarea name="body" required minlength="20" maxlength="4000" rows="3" class="mt-1 w-full rounded-[var(--radius-sm)] border border-[var(--rule)] bg-[var(--card)] p-3 text-xs text-[var(--ink)]" placeholder="How can mypage.ng help? (at least 20 characters)"></textarea>
                </label>
                <button class="w-fit rounded-[var(--radius-sm)] bg-[var(--brand)] px-4 py-2 text-xs font-bold text-[var(--brand-ink)]">Send to support</button>
            </form>
        </details>
    </div>
</footer>
