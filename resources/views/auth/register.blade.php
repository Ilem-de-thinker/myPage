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

        <div class="space-y-3 text-[11px] leading-tight text-slate-200">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required class="mt-0.5 rounded border-rule text-brand focus:ring-brand">
                <span>I agree to the <a href="{{ route('legal.policy', 'terms') }}" target="_blank" rel="noopener" class="text-brand underline">Terms of Service</a>.</span>
            </label>
            @error('terms_accepted') <p class="text-danger-text">{{ $message }}</p> @enderror
            <label class="flex items-start gap-2">
                <input type="checkbox" name="privacy_accepted" value="1" @checked(old('privacy_accepted')) required class="mt-0.5 rounded border-rule text-brand focus:ring-brand">
                <span>I have read the <a href="{{ route('legal.policy', 'privacy') }}" target="_blank" rel="noopener" class="text-brand underline">Privacy Policy</a>.</span>
            </label>
            @error('privacy_accepted') <p class="text-danger-text">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-2xl border border-brand/30 bg-brand/10 p-4 text-slate-100" x-data="{ enabled: @jsExpr((bool) old('ai_enabled', true)) }">
            <div class="flex items-start gap-3">
                <input type="checkbox" name="ai_enabled" value="1" x-model="enabled" @checked(old('ai_enabled', true)) class="mt-0.5 rounded border-rule text-brand focus:ring-brand">
                <div>
                    <p class="text-xs font-bold">Set up Mercho for my business</p>
                    <p class="mt-1 text-[11px] leading-5 text-slate-300">Choose how Mercho should help while you set up your store or service. You can change this later.</p>
                    <select name="ai_goal" class="mt-3 w-full rounded-xl border border-rule-strong bg-card px-3 py-2 text-xs text-ink" x-show="enabled">
                        <option value="sell_more" @selected(old('ai_goal', 'sell_more') === 'sell_more')>Help me write listings that sell</option>
                        <option value="write_faster" @selected(old('ai_goal') === 'write_faster')>Help me create my page faster</option>
                        <option value="answer_buyers" @selected(old('ai_goal') === 'answer_buyers')>Help answer buyer questions</option>
                    </select>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all flex items-center justify-center gap-2">
            <span>Continue to 60s Store Setup</span>
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </button>
    </form>

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
            <p class="mt-2 text-center text-[10px] leading-4 text-ink-3">
                By continuing with Google you agree to the
                <a href="{{ route('legal.policy', 'terms') }}" class="underline">Terms of Service</a>
                and
                <a href="{{ route('legal.policy', 'privacy') }}" class="underline">Privacy Policy</a>.
            </p>
        </div>
    @endif

    <p class="text-center text-xs text-ink-2 mt-6">Already have an account? <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">Log in here</a></p>
@endsection
