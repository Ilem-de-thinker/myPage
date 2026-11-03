@extends('layouts.guest')

@section('content')
    <div class="space-y-2 mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink tracking-tight">
            {{ $hasPassword ? 'Change your password' : 'Set a password' }}
        </h1>
        <p class="text-xs sm:text-sm text-ink-2">
            @if ($hasPassword)
                Choose a new password for signing in with your email address.
            @else
                Your shop signs in with Google. You can add a password as a second way in, or keep using Google.
            @endif
        </p>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-xl bg-brand-wash px-4 py-3 text-xs font-semibold text-brand-text">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
        @csrf

        @if ($hasPassword)
            <div>
                <label for="current_password" class="block text-xs font-semibold text-ink mb-1.5">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                       class="w-full px-4 py-3 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-ink">
                @error('current_password') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label for="password" class="block text-xs font-semibold text-ink mb-1.5">New password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password"
                   class="w-full px-4 py-3 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-ink">
            @error('password') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold text-ink mb-1.5">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                   class="w-full px-4 py-3 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 text-ink">
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all">
            {{ $hasPassword ? 'Update password' : 'Set password' }}
        </button>
    </form>

    <p class="mt-6 text-center text-xs text-ink-3">
        <a href="{{ route('dashboard') }}" class="font-semibold text-brand hover:underline">Back to dashboard</a>
    </p>
@endsection
