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
            <div class="flex justify-between p-4"><dt class="text-ink-2">WhatsApp</dt><dd class="font-semibold text-ink">{{ $data['whatsapp'] ?? 'Add later' }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">Location</dt><dd class="font-semibold text-ink">{{ trim(($data['city'] ?? '').', '.($data['state'] ?? ''), ', ') }}</dd></div>
            <div class="flex justify-between p-4"><dt class="text-ink-2">Brand colour</dt><dd><span class="inline-block w-5 h-5 rounded-md border border-rule" style="background: {{ $data['brand_color'] ?? '#16a34a' }}"></span></dd></div>
            <div class="flex justify-between gap-4 p-4"><dt class="text-ink-2">Mercho setup</dt><dd class="text-right font-semibold text-ink">{{ ($data['ai_enabled'] ?? true) ? 'Enabled' : 'Disabled' }}{{ ! empty($data['ai_goal']) ? ' · '.str_replace('_', ' ', $data['ai_goal']) : '' }}</dd></div>
            @if (! empty($data['ai_result']))
                <div class="p-4"><dt class="text-ink-2">Mercho review</dt><dd class="mt-2 text-xs leading-5 text-ink-2">{{ ucfirst($data['ai_result']['business_type'] ?? 'store') }} direction · {{ $data['ai_result']['tagline'] ?? 'No tagline' }}<div class="mt-3 grid gap-2 sm:grid-cols-2"><p class="rounded-lg bg-surface p-3">{{ $data['ai_result']['about'] ?? 'No about copy' }}</p><p class="rounded-lg bg-surface p-3">{{ count($data['ai_result']['categories'] ?? []) }} categories proposed</p></div></dd></div>
            @endif
        </dl>

        <a href="{{ route('onboarding.show', 'brand') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-3 hover:text-ink">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Back
        </a>

        <form method="POST" action="{{ route('onboarding.store', 'review') }}">
            @csrf
            @if (! empty($data['ai_result']['listings']))
                <fieldset class="mb-4 rounded-2xl border border-rule bg-card p-5"><legend class="px-1 text-sm font-bold text-ink">Starter drafts</legend><p class="mt-1 text-xs text-ink-3">Keep or remove each proposed item. They remain drafts after confirmation.</p><div class="mt-3 grid gap-2">@foreach ($data['ai_result']['listings'] as $index => $listing)<label class="flex items-start gap-3 rounded-xl border border-rule p-3"><input type="checkbox" name="starter_keep[]" value="{{ $index }}" checked class="mt-1 size-4 accent-brand"><span class="text-xs"><strong class="text-ink">{{ $listing['name'] ?? 'Untitled' }}</strong><span class="mt-1 block text-ink-2">{{ $listing['description'] ?? '' }}</span></span></label>@endforeach</div></fieldset>
            @endif
            <button type="submit" class="w-full py-3.5 px-4 bg-brand hover:bg-brand-hover text-white font-bold text-sm rounded-xl shadow-sm transition-all">Create my {{ $theme->siteNoun() }}</button>
        </form>
    </div>
@endsection
