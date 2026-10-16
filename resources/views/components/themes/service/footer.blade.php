@props(['page'])

@php
    use App\Shared\Support\StoreUrl;
    use App\Shared\Support\SocialLinks;

    $storefront = ['slug' => $page->slug];
    $home = StoreUrl::home($page);

    // The design lists named treatments under the wordmark. Derived from the
    // seller's own services so the footer never advertises something removed.
    $services = $page->services()->where('is_active', true)->limit(5)->get();
    $where = collect([$page->address, $page->city, $page->state])->filter()->join(', ');

    // Only networks the seller actually filled in render a link.
    $social = SocialLinks::forPage($page);
@endphp

<footer class="border-t border-[var(--rule)] bg-[var(--surface)] text-xs text-[var(--ink-2)] py-14">
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
                <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Service Menu</h4>
                <ul class="space-y-2 text-xs">
                    @forelse ($services as $service)
                        <li><a href="{{ route('storefront.service', array_merge($storefront, ['serviceSlug' => $service->slug])) }}" class="hover:text-[var(--ink)] transition-colors">{{ $service->name }}</a></li>
                    @empty
                        <li><a href="{{ route('storefront.services', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">See the full menu</a></li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Studio &amp; Escrow</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="{{ route('storefront.book', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Reserve Appointment Slot</a></li>
                    <li><a href="{{ route('storefront.manage-booking', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Manage Booking Reference</a></li>
                    @if ($page->bio)
                        <li><a href="{{ route('storefront.about', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">About the Studio</a></li>
                    @endif
                    <li><a href="{{ route('storefront.contact', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Studio Directions &amp; Hours</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-bold text-[var(--ink)] mb-3">Client Booking Portal</h4>
                <p class="text-xs text-[var(--ink-3)] leading-relaxed mb-3">
                    Clients never need a password. Access your appointment schedule and escrow retainer using your WhatsApp phone number.
                </p>
                <a href="{{ route('storefront.manage-booking', $storefront) }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-[var(--radius)] bg-[var(--paper)] border border-[var(--rule)] text-xs font-bold text-[var(--ink)] hover:border-[var(--brand-edge)] transition-colors">
                    <svg class="w-3.5 h-3.5 text-[var(--brand)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Client Booking Sign-In</span>
                </a>
            </div>
        </div>

        <div class="pt-8 border-t border-[var(--rule)] flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-[var(--ink-3)]">
            <p>© {{ now()->year }} {{ $page->name }}. Powered by <a href="{{ route('home') }}" class="underline hover:text-[var(--ink)]">mypage.ng</a> sovereign infrastructure.</p>
            <div class="flex items-center gap-6">
                <a href="{{ route('storefront.about', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">About</a>
                <a href="{{ route('storefront.contact', $storefront) }}" class="hover:text-[var(--ink)] transition-colors">Contact</a>

                {{-- Buyers never need an account, so this is the only sign-in door
                     on the page. Once the seller is signed in it is their studio. --}}
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                   class="hover:text-[var(--ink)] transition-colors">{{ auth()->check() ? 'Manage your page' : 'Sell on mypage.ng' }}</a>
            </div>
        </div>
    </div>
</footer>