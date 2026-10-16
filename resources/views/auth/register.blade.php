@extends('layouts.guest')

@section('content')
    <div class="space-y-2 mb-6">
        <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink tracking-tight">Start your WhatsApp shop</h1>
        <p class="text-xs sm:text-sm text-ink-2">Join 12,000+ Nigerian sellers. Free setup in under 60 seconds.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="block text-xs font-semibold text-white mb-1">Your Full Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Amaka Okafor"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                @error('name') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone" class="block text-xs font-semibold text-white mb-1">WhatsApp Number</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required placeholder="+234 803 456 7890"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 ">
                @error('phone') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold text-white mb-1">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="seller@store.ng"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 ">
            @error('email') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold text-white mb-1">Choose Password</label>
            <input type="password" id="password" name="password" required placeholder="At least 8 characters"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 ">
            @error('password') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold text-white mb-1">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required
                   class="w-full px-3.5 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/10 ">
        </div>

        <div class="text-[11px] text-slate-200 leading-tight">
            By creating your shop, you agree to our <a href="#" class="text-brand underline">Terms of Service</a> and NDPR compliant data privacy policy.
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
            <span>Continue to 60s Store Setup</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </button>
    </form>

    <p class="text-center text-xs text-ink-2 mt-6">Already have an account? <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">Log in here</a></p>
@endsection
