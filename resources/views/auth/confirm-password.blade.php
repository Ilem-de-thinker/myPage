@extends('layouts.guest')

@section('content')
    <div class="space-y-2 mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink tracking-tight">Confirm it is you</h1>
        <p class="text-xs sm:text-sm text-ink-2">
            This changes where your customers' money goes, so we ask again before saving it.
        </p>
    </div>

    @if ($usesGoogle)
        <p class="text-xs sm:text-sm text-ink-2 leading-relaxed">
            Your shop signs in with Google, so there is no password to type. Continue with Google and we will
            bring you back to the screen you were on.
        </p>

        <a href="{{ route('auth.google.confirm') }}"
           class="mt-6 w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
            Continue with Google
        </a>
    @else
        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
            @csrf

            <div>
                <label for="password" class="block text-xs font-semibold text-ink mb-1.5">Your password</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="current-password"
                       placeholder="••••••••••••"
                       class="w-full px-4 py-3 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-ink">
                @error('password') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all">
                Confirm and continue
            </button>
        </form>
    @endif
@endsection
