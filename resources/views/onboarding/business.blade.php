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
        <form method="POST" action="{{ route('onboarding.ai') }}" class="rounded-2xl border border-brand-edge bg-brand-wash p-6 sm:p-8 space-y-4">
            @csrf
            <div><h2 class="font-display text-lg font-bold text-ink">Create with AI</h2><p class="mt-1 text-xs leading-5 text-ink-2">Only your business name and one sentence about what you do are needed. You will review everything before anything is saved.</p></div>
            <input type="text" name="name" value="{{ $data['name'] ?? '' }}" required maxlength="120" placeholder="Business name" class="w-full rounded-xl border border-rule-strong bg-card px-4 py-2.5 text-sm">
            <textarea name="description" required maxlength="3000" rows="3" placeholder="What does your business sell or do?" class="w-full rounded-xl border border-rule-strong bg-card p-3 text-sm"></textarea>
            <button type="submit" class="min-h-11 rounded-xl bg-brand px-5 py-3 text-xs font-bold text-brand-ink">Create with AI</button>
        </form>
    </div>
@endsection
