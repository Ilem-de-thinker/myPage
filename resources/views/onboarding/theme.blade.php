@extends('onboarding.layout')

@section('content')
    <div class="space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink">Step 2: Website Template</h1>
            <p class="text-xs sm:text-sm text-ink-2">Pick the template that matches your business. You can change it later.</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store', 'theme') }}" class="bg-card p-6 sm:p-8 rounded-2xl border border-rule shadow-sm space-y-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="{ theme: '{{ $data['theme'] ?? '' }}' }">
                @foreach (['store' => ['Store', 'Sell products with a cart, variants and stock tracking.'], 'service' => ['Service', 'Take bookings and deposits for your services.']] as $value => [$label, $desc])
                    <label class="border-2 rounded-2xl p-5 cursor-pointer transition-colors"
                           :class="theme === '{{ $value }}' ? 'border-brand bg-brand-wash/40' : 'border-rule hover:border-rule-strong'">
                        <input type="radio" name="theme" value="{{ $value }}" class="sr-only" x-model="theme" @checked(($data['theme'] ?? '') === $value) required>
                        <span class="font-display font-bold text-ink block mb-1">{{ $label }}</span>
                        <span class="text-xs text-ink-2">{{ $desc }}</span>
                    </label>
                @endforeach
            </div>
            @error('theme') <p class="text-danger-text text-[11px]">{{ $message }}</p> @enderror

            <div class="pt-4 flex items-center justify-between">
                <a href="{{ route('onboarding.show', 'business') }}" class="text-sm font-semibold text-ink-3 hover:text-ink flex items-center gap-1.5">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Back
                </a>
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-sm transition-colors flex items-center gap-2">
                    <span>Continue to Brand</span>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
        </form>
    </div>
@endsection
