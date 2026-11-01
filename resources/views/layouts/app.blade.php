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
<body x-data="{ mobileSidebarOpen: false }" @keydown.escape.window="mobileSidebarOpen = false" class="min-h-screen flex antialiased selection:bg-brand-wash selection:text-brand-text pb-16 lg:pb-0">
    <div x-cloak x-show="mobileSidebarOpen" x-transition.opacity @click="mobileSidebarOpen = false" class="fixed inset-0 z-40 bg-ink/40 lg:hidden" aria-hidden="true"></div>

    <aside :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-50 flex w-[min(20rem,calc(100vw-2rem))] -translate-x-full flex-col justify-between border-r border-rule bg-card shadow-2xl transition-transform duration-200 ease-out lg:z-30 lg:w-64 lg:shadow-none">
        <div>
            <div class="h-18 px-5 flex items-center justify-between border-b border-rule py-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm">
                        <x-ui.icon name="storefront" size="size-5" stroke="2.1" />
                    </div>
                    <div>
                        <span class="font-display font-bold text-base text-ink">mypage<span class="text-brand">.ng</span></span>
                        <p class="text-[10px] text-ink-3 leading-none">Seller Workspace</p>
                    </div>
                </a>
                <button type="button" @click="mobileSidebarOpen = false" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-ink-2 hover:bg-tint lg:hidden" aria-label="Close navigation menu">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" /></svg>
                </button>
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

            <nav @click="mobileSidebarOpen = false" class="max-h-[calc(100dvh-9rem)] overflow-y-auto px-3 space-y-1 text-xs font-medium">
                <x-ui.nav-link :href="route('dashboard')" label="Dashboard" icon="dashboard" :active="request()->routeIs('dashboard')" />
                <x-ui.nav-link :href="route('storefront.edit')" label="My website" icon="storefront" :active="request()->routeIs('storefront.*')" />
                {{-- Only shown once the platform has switched the feature on, so a
                     soft launch does not advertise a page that 404s. --}}
                @if (\App\Domains\Billing\Services\Feature::enabled('custom_domains'))
                    <a href="{{ route('domains.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:text-ink hover:bg-tint transition-colors"><span>Your domain</span></a>
                @endif
                @if (in_array($page?->businessType() ?? 'store', ['service', 'both'], true))
                    <x-ui.nav-link :href="route('services.index')" label="Services" icon="briefcase" :active="request()->routeIs('services.*')" />
                    <x-ui.nav-link :href="route('staff.index')" label="Team" icon="users" :active="request()->routeIs('staff.*')" />
                    <x-ui.nav-link :href="route('bookings.index')" label="Bookings" icon="calendar" :active="request()->routeIs('bookings.*')" />
                    <x-ui.nav-link :href="route('availability.index', ['staff' => null])" label="Availability" icon="clock" :active="request()->routeIs('availability.*')" />
                @endif
                @if (in_array($page?->businessType() ?? 'store', ['store', 'both'], true))
                    <x-ui.nav-link :href="route('products.index')" label="Products & Stock" icon="shopping-bag" :active="request()->routeIs('products.*')" />
                    <x-ui.nav-link :href="route('categories.index')" label="Categories" icon="tag" :active="request()->routeIs('categories.*')" />
                    <x-ui.nav-link :href="route('dashboard.orders.index')" label="Orders" icon="receipt" :active="request()->routeIs('dashboard.orders.*')" />
                    <x-ui.nav-link :href="route('abandoned-carts.index')" label="Abandoned Carts" icon="cart" :active="request()->routeIs('abandoned-carts.*')" />
                @endif
                <x-ui.nav-link :href="route('dashboard.chat.index')" label="Customer chat" icon="message" :active="request()->routeIs('dashboard.chat.*')" />
                <x-ui.nav-link :href="route('dashboard.analytics.index')" label="Analytics" icon="chart" :active="request()->routeIs('dashboard.analytics.*')" />
                <x-ui.nav-link :href="route('customers.index')" label="Customers" icon="user" :active="request()->routeIs('customers.*')" />

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
                @php $pendingReviews = $page?->reviews()->awaitingModeration()->count() ?? 0; @endphp
                <a href="{{ route('reviews.index') }}"
                   @class(['group flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-xs font-medium transition-colors', 'bg-brand-wash font-semibold text-brand-text' => request()->routeIs('reviews.*'), 'text-ink-2 hover:bg-tint hover:text-ink' => ! request()->routeIs('reviews.*')])
                   @if(request()->routeIs('reviews.*')) aria-current="page" @endif>
                    <span class="flex items-center gap-3"><x-ui.icon name="star" class="size-4 shrink-0" />Reviews</span>
                    @if ($pendingReviews > 0)
                        <span class="rounded-full bg-brand px-1.5 py-0.5 text-[10px] font-bold text-white tabular-nums">{{ $pendingReviews }}</span>
                    @endif
                </a>

                <x-ui.nav-link :href="route('billing.index')" label="Payments" icon="credit-card" :active="request()->routeIs('billing.*')" />
                <x-ui.nav-link :href="route('payments.gateways.index')" label="Payment keys" icon="key" :active="request()->routeIs('payments.gateways.*')" />
                <x-ui.nav-link :href="route('support.index')" label="Support" icon="life-buoy" :active="request()->routeIs('support.*')" />
                <x-ui.nav-link :href="route('badge.apply')" label="Get verified" icon="badge" :active="request()->routeIs('badge.*')" />
                @if (auth()->user()->isAdmin())
                    <x-ui.nav-link :href="route('admin.index')" label="Admin panel" icon="shield" :active="request()->routeIs('admin.*')" />
                @endif
            </nav>
        </div>

        <div class="p-4">
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg text-ink-2 hover:bg-tint text-xs font-medium">Log out</button>
            </form>
            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 px-3 text-[10px] text-ink-3">
                <a href="{{ route('legal.policy', 'privacy') }}" class="hover:text-ink">Privacy</a>
                <a href="{{ route('legal.policy', 'terms') }}" class="hover:text-ink">Terms</a>
                <a href="{{ route('legal.policy', 'contact') }}" class="hover:text-ink">Contact</a>
            </div>
        </div>
    </aside>

    <div class="flex-1 lg:ml-64">
        <header class="bg-card-glass backdrop-blur border-b border-rule sticky top-0 z-20 lg:hidden">
            <div class="px-4 h-14 flex items-center justify-between gap-3">
                <button type="button" @click="mobileSidebarOpen = true" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-rule text-ink-2 hover:bg-tint" aria-label="Open navigation menu" :aria-expanded="mobileSidebarOpen">
                    <x-ui.icon name="menu" size="size-5" />
                </button>
                <span class="min-w-0 flex-1 truncate text-center font-display font-bold text-ink">mypage<span class="text-brand">.ng</span></span>
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
            @php
                $unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(3)->get();
            @endphp
            @if ($unreadNotifications->isNotEmpty())
                <section class="mb-4 rounded-2xl border border-warn-edge bg-warn-wash px-4 py-3 text-warn-text" aria-label="Account notifications">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 space-y-2">
                            @foreach ($unreadNotifications as $notification)
                                @php
                                    $notice = $notification->data;
                                @endphp
                                <div class="text-xs leading-5">
                                    <p class="font-bold">{{ $notice['title'] ?? 'Account notice' }}</p>
                                    <p>{{ $notice['body'] ?? '' }}</p>
                                    @if (! empty($notice['url']))
                                        <a href="{{ $notice['url'] }}" class="font-bold underline">{{ $notice['action'] ?? 'Review' }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('notifications.read') }}">
                            @csrf
                            <button type="submit" class="min-h-9 rounded-lg border border-warn-edge bg-card/60 px-3 text-[11px] font-bold text-warn-text">Mark as read</button>
                        </form>
                    </div>
                </section>
            @endif
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-ok-wash border border-ok-edge text-ok-text text-xs px-4 py-3">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs px-4 py-3">
                    <p class="font-semibold">Please review the highlighted information.</p>
                    <ul class="mt-1 list-disc pl-4 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-rule bg-card/95 pb-[env(safe-area-inset-bottom)] text-[10px] text-ink-3 backdrop-blur lg:hidden" aria-label="Mobile navigation">
        <a href="{{ route('dashboard') }}" @class(['flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-[10px] font-semibold', 'text-brand' => request()->routeIs('dashboard'), 'text-ink-3' => ! request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-ui.icon name="dashboard" class="size-4" />Home</a>
        <a href="{{ route('dashboard.chat.index') }}" @class(['flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-[10px] font-semibold', 'text-brand' => request()->routeIs('dashboard.chat.*'), 'text-ink-3' => ! request()->routeIs('dashboard.chat.*')]) @if(request()->routeIs('dashboard.chat.*')) aria-current="page" @endif><x-ui.icon name="message" class="size-4" />Inbox</a>
        @if (in_array($page?->businessType() ?? 'store', ['store', 'both'], true))
            <a href="{{ route('products.index') }}" @class(['flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-[10px] font-semibold', 'text-brand' => request()->routeIs('products.*'), 'text-ink-3' => ! request()->routeIs('products.*')]) @if(request()->routeIs('products.*')) aria-current="page" @endif><x-ui.icon name="shopping-bag" class="size-4" />Items</a>
        @else
            <a href="{{ route('services.index') }}" @class(['flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-[10px] font-semibold', 'text-brand' => request()->routeIs('services.*'), 'text-ink-3' => ! request()->routeIs('services.*')]) @if(request()->routeIs('services.*')) aria-current="page" @endif><x-ui.icon name="briefcase" class="size-4" />Services</a>
        @endif
        <button type="button" @click="mobileSidebarOpen = true" class="flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-[10px] font-semibold text-ink-3"><x-ui.icon name="menu" class="size-4" />More</button>
    </nav>

    @php
        $aiPageFeature = \App\Domains\Ai\Support\AiFeatureRegistry::forRequest(request());
        $aiPageAllowed = $aiPageFeature && $page && \App\Domains\Ai\Support\AiFeatureRegistry::allowed($page, $aiPageFeature);
    @endphp
    <x-support.floating-button seller :ai-feature="$aiPageAllowed ? $aiPageFeature : null" />

    @yield('scripts')
</body>
</html>
