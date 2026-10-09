@extends('layouts.guest')

@section('content')
    <div class="space-y-2 mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink tracking-tight">Welcome back to your shop</h1>
        <p class="text-xs sm:text-sm text-body">Log in to manage orders, update your catalog, and check payouts.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="seller@store.ng"
                   class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-slate-800">
            @error('email') <p class="text-red-600 text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs font-semibold text-slate-700">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs text-brand hover:underline font-medium">Forgot password?</a>
            </div>
            <input type="password" id="password" name="password" required placeholder="••••••••••••"
                   class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-slate-800">
        </div>

        <div class="flex items-center justify-between text-xs pt-1">
            <label class="flex items-center gap-2 cursor-pointer text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded text-brand focus:ring-brand w-4 h-4 border-slate-300">
                <span>Keep me signed in</span>
            </label>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-dark text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
            <span>Log in to Dashboard</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </button>
    </form>

    <p class="text-center text-xs text-body mt-6">Don't have a shop yet? <a href="{{ route('register') }}" class="text-brand font-semibold hover:underline">Create a free shop</a></p>
@endsection
