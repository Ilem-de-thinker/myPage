@extends('layouts.guest')

@section('content')
    <div class="space-y-2 mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink tracking-tight">Welcome back to your shop</h1>
        <p class="text-xs sm:text-sm text-ink-2">Log in to manage orders, update your catalogue, and check your payments.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-xs font-semibold text-ink mb-1.5">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="seller@store.ng"
                   class="w-full px-4 py-3 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-ink">
            @error('email') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs font-semibold text-ink">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs text-brand hover:underline font-medium">Forgot password?</a>
            </div>
            <input type="password" id="password" name="password" required placeholder="••••••••••••"
                   class="w-full px-4 py-3 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-ink">
        </div>

        <div class="flex items-center justify-between text-xs pt-1">
            <label class="flex items-center gap-2 cursor-pointer text-ink-2">
                <input type="checkbox" name="remember" value="1" class="rounded text-brand focus:ring-brand w-4 h-4 border-rule-strong">
                <span>Keep me signed in</span>
            </label>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
            <span>Log in to Dashboard</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </button>
    </form>

    {{--
        Google sign-in.

        Only when there is a client id to use. A button that leads to a 404 is
        worse than no button, and the callback URL still has to be registered in
        the Google console before this works at all — so an install with the keys
        but no console entry gets the button and a clear failure, and one with
        neither gets nothing.
    --}}
    @if (filled(config('services.google.client_id')))
        <div class="mt-6">
            <div class="flex items-center gap-3">
                <span class="h-px flex-1 bg-rule"></span>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-3">or</span>
                <span class="h-px flex-1 bg-rule"></span>
            </div>

            <a href="{{ route('auth.google') }}"
               class="mt-4 w-full py-3.5 px-4 bg-card border border-rule-strong hover:bg-surface text-ink font-bold text-sm rounded-xl transition-colors flex items-center justify-center gap-3">
                <svg class="w-4 h-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.63h6.46a5.52 5.52 0 0 1-2.4 3.62v3h3.88c2.27-2.09 3.58-5.17 3.58-8.8z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.94-2.91l-3.88-3.01c-1.08.72-2.45 1.15-4.06 1.15-3.13 0-5.78-2.11-6.73-4.95H1.28v3.1A12 12 0 0 0 12 24z"/>
                    <path fill="#FBBC05" d="M5.27 14.28a7.2 7.2 0 0 1 0-4.56v-3.1H1.28a12 12 0 0 0 0 10.76l3.99-3.1z"/>
                    <path fill="#EA4335" d="M12 4.75c1.76 0 3.34.61 4.59 1.8l3.43-3.43C17.95 1.19 15.24 0 12 0A12 12 0 0 0 1.28 6.62l3.99 3.1C6.22 6.87 8.87 4.75 12 4.75z"/>
                </svg>
                <span>Continue with Google</span>
            </a>
        </div>
    @endif

    <p class="text-center text-xs text-ink-2 mt-6">Don't have a shop yet? <a href="{{ route('register') }}" class="text-brand font-semibold hover:underline">Create a free shop</a></p>
@endsection
