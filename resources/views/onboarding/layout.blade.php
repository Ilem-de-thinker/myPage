<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Onboarding — mypage.ng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface flex flex-col antialiased">
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center text-white">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <span class="font-display font-bold text-lg text-ink">mypage<span class="text-brand">.ng</span></span>
            </div>

            <div class="flex items-center gap-2 sm:gap-6 text-xs">
                @php
                    $steps = ['business' => 'Business Basics', 'theme' => 'Theme & Logo', 'brand' => 'Brand', 'review' => 'Go Live'];
                    $keys = array_keys($steps);
                    $current = array_search(request()->route('step'), $keys);
                @endphp
                @foreach ($steps as $key => $label)
                    @if (! $loop->first) <span class="text-slate-300">―</span> @endif
                    <div class="flex items-center gap-1.5">
                        <span class="w-6 h-6 rounded-full {{ array_search($key, $keys) <= $current ? 'bg-brand text-white' : 'bg-slate-200 text-slate-600' }} flex items-center justify-center font-bold text-xs">{{ $loop->iteration }}</span>
                        <span class="hidden sm:inline {{ array_search($key, $keys) <= $current ? 'font-semibold text-slate-800' : 'font-medium text-slate-500' }}">{{ $label }}</span>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-slate-500 hover:text-ink">Skip setup</a>
        </div>
    </header>

    <main class="flex-1 py-10 px-4">
        <div class="max-w-2xl mx-auto">
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs px-4 py-3">{{ session('status') }}</div>
            @endif
            @yield('content')
        </div>
    </main>
</body>
</html>
