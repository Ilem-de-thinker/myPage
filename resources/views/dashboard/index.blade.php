@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Dashboard" :subtitle="'Welcome back, '.auth()->user()->name.'.'" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach (['Page Views' => '0', 'Orders This Week' => '0', 'Revenue' => '₦0', 'Upcoming Bookings' => '0'] as $label => $value)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-subtle">
                    <p class="text-[11px] text-body uppercase tracking-wide">{{ $label }}</p>
                    <p class="text-2xl font-display font-extrabold text-ink mt-1 tabular-naira">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($page)
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] text-body uppercase tracking-wide">Your storefront</p>
                        <h2 class="text-xl font-display font-bold text-ink mt-1">{{ $page->name }}</h2>
                        <p class="text-xs text-body mt-1">{{ \App\Shared\Support\StoreUrl::home($page) }} · {{ $page->theme->label() }} theme · <span class="font-semibold">{{ $page->status->value }}</span></p>
                    </div>
                    <a href="{{ \App\Shared\Support\StoreUrl::forSeller($page) }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-xs shrink-0">
                        {{ $page->isPublished() ? 'View store' : 'Preview store' }}
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h3 class="font-display font-bold text-ink mb-3">Get started</h3>
                <ul class="text-sm space-y-2 text-slate-600">
                    <li>1. Add your first {{ $page->theme->value === 'store' ? 'product' : 'service' }}</li>
                    <li>2. Set your WhatsApp number and city</li>
                    <li>3. Preview and publish your page</li>
                    <li>4. Share your link with customers</li>
                </ul>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 p-6 text-body text-sm">No store yet — <a href="{{ route('onboarding.show', 'business') }}" class="text-brand font-semibold">create your storefront</a>.</div>
        @endif
    </div>
@endsection
