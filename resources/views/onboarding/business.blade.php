@extends('onboarding.layout')

@section('content')
    <div class="space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink">Step 1: Your Business Basics</h1>
            <p class="text-xs sm:text-sm text-ink-2">This information will be shown at the top of your website and pre-filled for buyer messages.</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store', 'business') }}" class="bg-card p-6 sm:p-8 rounded-2xl border border-rule shadow-sm space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">Business Name</label>
                <input type="text" name="name" value="{{ $data['name'] ?? '' }}" required class="w-full px-4 py-2.5 rounded-xl border border-rule-strong text-sm focus:outline-none focus:border-brand">
                @error('name') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink mb-1.5">Website Address</label>
                <div class="flex items-center rounded-xl border border-rule-strong px-3 bg-surface focus-within:border-brand focus-within:bg-card">
                    <span class="text-xs text-ink-3 select-none">mypage.ng/</span>
                    <input type="text" name="slug" value="{{ $data['slug'] ?? '' }}" required placeholder="yourshop"
                           class="w-full py-2.5 pl-1 text-sm bg-transparent border-none focus:outline-none font-semibold text-brand">
                </div>
                @error('slug') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-sm transition-colors flex items-center gap-2">
                    <span>Continue to Theme & Logo</span>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
        </form>
    </div>
@endsection
