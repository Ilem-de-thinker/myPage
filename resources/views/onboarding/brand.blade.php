@extends('onboarding.layout')

@section('content')
    <div class="space-y-6">
        @php
            $isService = $theme === \App\Domains\Seller\Enums\Theme::Service;
        @endphp
        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink">Step 3: Brand & Contact</h1>
            <p class="text-xs sm:text-sm text-ink-2">How your {{ $theme->siteNoun() }} looks and where customers reach you.</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store', 'brand') }}" class="bg-card p-6 sm:p-8 rounded-2xl border border-rule shadow-sm space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">Brand Colour</label>
                <input type="color" name="brand_color" value="{{ $data['brand_color'] ?? '#16a34a' }}" class="h-11 w-20 rounded-xl border border-rule-strong">
                @error('brand_color') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">Connected WhatsApp Business Phone</label>
                <input type="tel" name="whatsapp" value="{{ $data['whatsapp'] ?? '' }}" required placeholder="+234 803 456 7890" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand font-medium">
                <p class="text-[11px] text-ink-3 mt-1">This is where new {{ $isService ? 'booking requests' : 'orders' }} will arrive from customers.</p>
                @error('whatsapp') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1.5">City</label>
                    <input type="text" name="city" value="{{ $data['city'] ?? '' }}" required class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                    @error('city') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1.5">State</label>
                    <input type="text" name="state" value="{{ $data['state'] ?? '' }}" required class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                    @error('state') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">Short {{ $isService ? 'Business' : 'Store' }} Bio</label>
                <textarea name="bio" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">{{ $data['bio'] ?? '' }}</textarea>
                @error('bio') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4 flex items-center justify-between">
                <a href="{{ route('onboarding.show', 'theme') }}" class="text-sm font-semibold text-ink-3 hover:text-ink flex items-center gap-1.5">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Back
                </a>
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-sm transition-colors flex items-center gap-2">
                    <span>Review & Go Live</span>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
        </form>
    </div>
@endsection
