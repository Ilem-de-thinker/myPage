<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — mypage.ng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex antialiased selection:bg-brand-soft selection:text-brand-900 pb-16 lg:pb-0">
    <aside class="hidden lg:flex w-64 flex-col bg-white border-r border-slate-200 fixed inset-y-0 z-30 justify-between">
        <div>
            <div class="h-18 px-5 flex items-center justify-between border-b border-slate-200 py-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <div>
                        <span class="font-display font-bold text-base text-ink">mypage<span class="text-brand">.ng</span></span>
                        <p class="text-[10px] text-slate-400 leading-none">Seller Workspace</p>
                    </div>
                </a>
            </div>

            @php $page = $page ?? auth()->user()?->storePages()->first(); @endphp
            <div class="p-4 mx-3 my-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
                <div class="truncate">
                    <div class="flex items-center gap-1">
                        <span class="font-bold text-xs text-ink truncate">{{ $page?->name ?? 'No store yet' }}</span>
                    </div>
                    <span class="text-[10px] text-emerald-700 font-medium">{{ $page ? $page->slug.'.mypage.ng' : '' }}</span>
                </div>
            </div>

            <nav class="px-3 space-y-1 text-xs font-medium">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-brand-soft text-brand-dark font-semibold">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('page.editor') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Page Builder</span></a>
                @if (($page?->theme?->value ?? 'store') === 'service')
                    <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Services</span></a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Bookings</span></a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Availability</span></a>
                @else
                    <a href="{{ route('products.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Products & Stock</span></a>
                    <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Orders</span></a>
                @endif
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Customers</span></a>
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:text-ink hover:bg-slate-100 transition-colors"><span>Payments</span></a>
            </nav>
        </div>

        <div class="p-4">
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-100 text-xs font-medium">Log out</button>
            </form>
        </div>
    </aside>

    <div class="flex-1 lg:ml-64">
        <header class="bg-white/80 backdrop-blur border-b border-slate-200 sticky top-0 z-20 lg:hidden">
            <div class="px-4 h-14 flex items-center justify-between">
                <span class="font-display font-bold text-ink">mypage<span class="text-brand">.ng</span></span>
                <form method="POST" action="{{ route('logout') }}">@csrf <button class="text-xs text-slate-500">Log out</button></form>
            </div>
        </header>

        <header class="hidden lg:flex bg-white/80 backdrop-blur border-b border-slate-200 sticky top-0 z-20 h-16 items-center justify-between px-8">
            <div class="flex items-center gap-3">
                <h1 class="text-sm font-bold text-ink">@yield('heading', ucfirst($page?->theme?->label() ?? 'Store').' Dashboard')</h1>
            </div>
            <div class="flex items-center gap-4">
                @if ($page)
                    <a href="{{ \App\Shared\Support\StoreUrl::forSeller($page) }}" target="_blank" class="text-xs font-semibold text-brand hover:text-brand-dark">View store ↗</a>
                @endif
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-brand-soft text-brand-dark flex items-center justify-center text-xs font-bold">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span class="text-xs font-medium text-slate-600">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </header>

        <main class="p-4 sm:p-8 max-w-6xl mx-auto">
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-4 py-3">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    <nav class="lg:hidden fixed bottom-0 inset-x-0 bg-white border-t border-slate-200 z-30 grid grid-cols-4 text-[10px] text-slate-500">
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-0.5 py-2 text-brand font-semibold">Home</a>
        <a href="#" class="flex flex-col items-center gap-0.5 py-2">Inbox</a>
        <a href="#" class="flex flex-col items-center gap-0.5 py-2">Items</a>
        <a href="#" class="flex flex-col items-center gap-0.5 py-2">More</a>
    </nav>
</body>
</html>
