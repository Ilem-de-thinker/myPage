<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'mypage.ng' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface text-slate-800 antialiased">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <div class="flex-1 flex flex-col justify-between p-6 sm:p-12 lg:p-16 max-w-xl mx-auto w-full">
            <div>
                <a href="/" class="inline-flex items-center gap-2.5 mb-8">
                    <div class="w-9 h-9 rounded-xl bg-brand flex items-center justify-center text-white shadow-sm">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <span class="font-display font-extrabold text-xl text-ink">mypage<span class="text-brand">.ng</span></span>
                </a>
                @if (session('status'))
                    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-4 py-3">{{ session('status') }}</div>
                @endif
                @yield('content')
            </div>
            <div class="pt-8 text-center sm:text-left text-xs text-slate-400">© 2026 mypage.ng · Nigeria's #1 Social Commerce Platform</div>
        </div>

        <div class="hidden lg:flex flex-1 bg-ink text-white p-16 flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-950 via-slate-900 to-ink opacity-90"></div>
            <div class="absolute -right-20 -bottom-20 w-96 h-96 bg-brand/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10">
                <span class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1 rounded-full bg-emerald-900/60 text-emerald-300 border border-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-brand"></span> Zero Setup Fee
                </span>
            </div>
            <div class="relative z-10 space-y-6 max-w-lg">
                <h2 class="text-3xl font-display font-extrabold text-white leading-snug">Turn your WhatsApp contacts into paying repeat buyers.</h2>
                <div class="space-y-4 text-sm text-slate-300">
                    <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">✓</div><span>Clean mobile catalog with your brand colors & logo</span></div>
                    <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">✓</div><span>No more checking fake alert receipts manually</span></div>
                    <div class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs shrink-0">✓</div><span>Escrow protection that gives doubtful buyers confidence</span></div>
                </div>
            </div>
            <div class="relative z-10 flex items-center justify-between text-xs text-slate-400 pt-8 border-t border-slate-800">
                <span>Trusted by merchants across 36 Nigerian states</span>
                <span class="text-emerald-400 font-medium">Ready in 60 seconds</span>
            </div>
        </div>
    </div>
</body>
</html>
