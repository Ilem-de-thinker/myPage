<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — mypage.ng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <x-ui.theme-boot />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex antialiased selection:bg-brand-wash selection:text-brand-text pb-16 lg:pb-0">
    <aside class="hidden lg:flex w-64 flex-col bg-card border-r border-rule fixed inset-y-0 z-30 justify-between">
        <div>
            <div class="h-18 px-5 flex items-center justify-between border-b border-rule py-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <div>
                        <span class="font-display font-bold text-base text-ink">mypage<span class="text-brand">.ng</span></span>
                        <p class="text-[10px] text-ink-3 leading-none">Seller Workspace</p>
                    </div>
                </a>
            </div>

            @php $page = $page ?? auth()->user()?->storePages()->first(); @endphp
            <div class="p-4 mx-3 my-3 bg-surface rounded-xl border border-rule/80 flex items-center justify-between">
                <div class="truncate">
                    <div class="flex items-center gap-1">
                        <span class="font-bold text-xs text-ink truncate">{{ $page?->name ?? 'No page yet' }}</span>
                    </div>
                    <span class="text-[10px] text-ok-text font-medium">{{ $page ? $page->slug.'.mypage.ng' : '' }}</span>
                </div>
            </div>

            <nav class="px-3 space-y-1 text-xs font-medium">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-brand-wash text-brand-text font-semibold">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('storefront.edit') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>My website</span></a>
                {{-- Only shown once the platform has switched the feature on, so a
                     soft launch does not advertise a page that 404s. --}}
                @if (\App\Domains\Billing\Services\Feature::enabled('custom_domains'))
                    <a href="{{ route('domains.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Your domain</span></a>
                @endif
                @if (($page?->theme?->value ?? 'store') === 'service')
                    <a href="{{ route('services.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Services</span></a>
                    <a href="{{ route('staff.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Team</span></a>
                    <a href="{{ route('bookings.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Bookings</span></a>
                    <a href="{{ route('availability.index', ['staff' => null]) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Availability</span></a>
                @else
                    <a href="{{ route('products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Products & Stock</span></a>
                    <a href="{{ route('categories.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Categories</span></a>
                    <a href="{{ route('dashboard.orders.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Orders</span></a>
                    <a href="{{ route('abandoned-carts.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Abandoned Carts</span></a>
                @endif
                <a href="{{ route('customers.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Customers</span></a>

                {{--
                    Reviews, with the waiting count on the nav item itself. The
                    whole feature is the moderation queue: a seller who has not
                    seen that number has not published anything, and would
                    reasonably conclude nobody had written to them.

                    Scoped to this page through the already-loaded $page rather
                    than counted globally: a nav badge counting other sellers'
                    queues is both wrong and a slow query on every dashboard
                    screen. One indexed COUNT, cheaper than the badge is worth.
                --}}
                @php $pendingReviews = $page->reviews()->awaitingModeration()->count(); @endphp
                <a href="{{ route('reviews.index') }}"
                   class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors">
                    <span>Reviews</span>
                    @if ($pendingReviews > 0)
                        <span class="rounded-full bg-brand px-1.5 py-0.5 text-[10px] font-bold text-white tabular-nums">{{ $pendingReviews }}</span>
                    @endif
                </a>

                <a href="{{ route('payouts.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Withdraw</span></a>
                <a href="{{ route('billing.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Payments</span></a>
                <a href="{{ route('badge.apply') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Get verified</span></a>
                @if (auth()->user()->is_admin)
                    <a href="{{ route('admin.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Admin panel</span></a>
                @endif
            </nav>
        </div>

        <div class="p-4">
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:bg-tint text-xs font-medium">Log out</button>
            </form>
        </div>
    </aside>

    <div class="flex-1 lg:ml-64">
        <header class="bg-card-glass backdrop-blur border-b border-rule sticky top-0 z-20 lg:hidden">
            <div class="px-4 h-14 flex items-center justify-between">
                <span class="font-display font-bold text-ink">mypage<span class="text-brand">.ng</span></span>
                <div class="flex items-center gap-2">
                    <x-ui.theme-toggle />
                    <form method="POST" action="{{ route('logout') }}">@csrf <button class="text-xs text-ink-3">Log out</button></form>
                </div>
            </div>
        </header>

        <header class="hidden lg:flex bg-card-glass backdrop-blur border-b border-rule sticky top-0 z-20 h-16 items-center justify-between px-8">
            <div class="flex items-center gap-3">
                <h1 class="text-sm font-bold text-ink">@yield('heading', ucfirst($page?->theme?->label() ?? 'Store').' Dashboard')</h1>
            </div>
            <div class="flex items-center gap-4">
                @if ($page)
                    <a href="{{ \App\Shared\Support\StoreUrl::forSeller($page) }}" target="_blank" class="text-xs font-semibold text-brand hover:text-brand-text">View {{ $page->theme->siteNoun() }} ↗</a>
                @endif
                <div class="flex items-center gap-2">
                    <x-ui.theme-toggle />
                    <div class="w-8 h-8 rounded-full bg-brand-wash text-brand-text flex items-center justify-center text-xs font-bold">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span class="text-xs font-medium text-ink-2">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </header>

        <main class="p-4 sm:p-8 max-w-6xl mx-auto">
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-ok-wash border border-ok-edge text-ok-text text-xs px-4 py-3">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    <nav class="lg:hidden fixed bottom-0 inset-x-0 bg-card border-t border-rule z-30 grid grid-cols-4 text-[10px] text-ink-3">
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-0.5 py-2 text-brand font-semibold">Home</a>
        <a href="#" class="flex flex-col items-center gap-0.5 py-2">Inbox</a>
        <a href="#" class="flex flex-col items-center gap-0.5 py-2">Items</a>
        <a href="#" class="flex flex-col items-center gap-0.5 py-2">More</a>
    </nav>
</body>
</html>
