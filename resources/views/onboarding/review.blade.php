@extends('onboarding.layout')

@section('content')
    <div class="space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-ink">Step 4: Go Live</h1>
            <p class="text-xs sm:text-sm text-ink-2">Confirm your details and we will create your {{ $theme->siteNoun() }} as a draft.</p>
        </div>

        <dl class="bg-card rounded-2xl border border-rule shadow-sm divide-y text-sm">
            <div class="flex justify-between p-4"><dt class="text-ink-2">Business</dt><dd class="font-semibold text-ink">{{ $data['name'] ?? '—' }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">Website address</dt><dd class="font-semibold text-ink">mypage.ng/{{ $data['slug'] ?? '—' }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">Template</dt><dd class="font-semibold text-ink">{{ $theme->label() }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">WhatsApp</dt><dd class="font-semibold text-ink">{{ $data['whatsapp'] ?? '—' }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">Location</dt><dd class="font-semibold text-ink">{{ trim(($data['city'] ?? '').', '.($data['state'] ?? ''), ', ') }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">Brand colour</dt><dd><span class="inline-block w-5 h-5 rounded-md border border-rule" style="background: {{ $data['brand_color'] ?? '#16a34a' }}"></span></dd></div>
        </dl>

        <a href="{{ route('onboarding.show', 'brand') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-3 hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>

        <form method="POST" action="{{ route('onboarding.store', 'review') }}">
            @csrf
            <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all">Create my {{ $theme->siteNoun() }}</button>
        </form>
    </div>
@endsection
