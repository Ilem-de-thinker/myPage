<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'mypage.ng' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <x-ui.theme-boot />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-ink antialiased">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <div class="flex-1 flex flex-col justify-between p-6 sm:p-12 lg:p-16 max-w-xl mx-auto w-full">
            <div>
                <div class="flex items-start justify-between gap-4 mb-8">
                    <a href="/" class="inline-flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <span class="font-display font-extrabold text-xl text-ink">mypage<span class="text-brand">.ng</span></span>
                    </a>

                    <x-ui.theme-toggle />
                </div>
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
            </div>
            <div class="pt-8 text-center text-xs text-ink-3 sm:text-left">
                © {{ now()->year }} mypage.ng ·
                <a href="{{ route('legal.policy', 'privacy') }}" class="hover:text-ink">Privacy</a> ·
                <a href="{{ route('legal.policy', 'terms') }}" class="hover:text-ink">Terms</a>
            </div>
        </div>

        <div class="hidden lg:flex flex-1 bg-ink text-white p-16 flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-950 via-slate-900 to-ink opacity-90"></div>
            <div class="absolute -right-20 -bottom-20 w-96 h-96 bg-brand/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10">
                
            </div>
            <div class="relative z-10 space-y-6 max-w-lg">
                <h2 class="text-3xl font-display font-extrabold text-white leading-snug">Turn your WhatsApp contacts into paying repeat buyers.</h2>
                <div class="space-y-4 text-sm text-ink-3">
                    <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">✓</div><span>Clean mobile catalog with your brand colors & logo</span></div>
                    <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">✓</div><span>No more checking fake alert receipts manually</span></div>
                    <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">✓</div><span>Payments that go straight to the seller, never through us</span></div>
                </div>
            </div>
            <div class="relative z-10 flex items-center justify-between text-xs text-ink-3 pt-8 border-t border-slate-800">
                <span>Trusted by merchants across 36 Nigerian states</span>
                <span class="text-emerald-400 font-medium">Ready in 60 seconds</span>
            </div>
        </div>
    </div>
</body>
</html>
