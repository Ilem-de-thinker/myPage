@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Dashboard" :subtitle="'Welcome back, '.auth()->user()->name.'.'" />

        @if ($blockers = session('publish_blockers'))
            <div class="bg-warn-wash border border-warn-edge rounded-2xl p-5">
                <p class="text-sm font-semibold text-warn-text">Almost there — your page is not live yet.</p>
                <ul class="mt-2 space-y-1 text-xs text-warn-text">
                    @foreach ($blockers as $blocker)
                        <li>
                            {{ $blocker['label'] }} — {{ $blocker['hint'] }}
                            @if (! empty($blocker['url']))
                                <a href="{{ $blocker['url'] }}" class="font-semibold underline">Fix this</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($page)
            @php
                $isService = $page->theme === \App\Domains\Seller\Enums\Theme::Service;
                $activityLabel = $page->theme->transactionNoun(true).' This Week';
                $activity = $stats['activity_this_week'];
                $activityLastWeek = $stats['activity_previous_week'];
                $weekDelta = $activity - $activityLastWeek;
            @endphp

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-card rounded-2xl border border-rule p-4 shadow-subtle">
                    <p class="text-[11px] text-ink-2 uppercase tracking-wide">{{ $activityLabel }}</p>
                    <p class="text-2xl font-display font-extrabold text-ink mt-1 tabular-naira">{{ $activity }}</p>
                    <p class="text-[11px] mt-1 {{ $weekDelta >= 0 ? 'text-ok-text' : 'text-warn-text' }}">
                        @if ($weekDelta == 0)
                            Same as last week
                        @else
                            {{ $weekDelta > 0 ? '+' : '' }}{{ $weekDelta }} vs last week
                        @endif
                    </p>
                </div>

                <div class="bg-card rounded-2xl border border-rule p-4 shadow-subtle">
                    <p class="text-[11px] text-ink-2 uppercase tracking-wide">Revenue This Week</p>
                    <p class="text-2xl font-display font-extrabold text-ink mt-1 tabular-naira">
                        {{ \App\Shared\Support\Money::format($stats['earned_this_week_kobo']) }}
                    </p>
                    <p class="text-[11px] text-ink-3 mt-1">
                        {{ $isService ? 'Kept appointments only' : 'Paid orders only' }}
                    </p>
                </div>

                <div class="bg-card rounded-2xl border border-rule p-4 shadow-subtle">
                    <p class="text-[11px] text-ink-2 uppercase tracking-wide">Customers</p>
                    <p class="text-2xl font-display font-extrabold text-ink mt-1 tabular-naira">{{ $stats['customers_total'] }}</p>
                    <p class="text-[11px] text-ink-3 mt-1">
                        {{ $stats['new_customers_this_week'] }} new this week
                    </p>
                </div>

                <div class="bg-card rounded-2xl border border-rule p-4 shadow-subtle">
                    <p class="text-[11px] text-ink-2 uppercase tracking-wide">
                        {{ $isService ? 'Upcoming Bookings' : 'Abandoned Carts' }}
                    </p>
                    <p class="text-2xl font-display font-extrabold text-ink mt-1 tabular-naira">{{ $stats['pending_count'] }}</p>
                    <p class="text-[11px] text-ink-3 mt-1">
                        {{ \App\Shared\Support\Money::format($stats['pending_value_kobo']) }}
                        {{ $isService ? 'still ahead' : 'left behind' }}
                    </p>
                </div>
            </div>

            <div class="bg-card rounded-2xl border border-rule p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] text-ink-2 uppercase tracking-wide">Your {{ $page->theme->siteNoun() }}</p>
                        <h2 class="text-xl font-display font-bold text-ink mt-1">{{ $page->name }}</h2>
                        <p class="text-xs text-ink-2 mt-1">
                            {{ \App\Shared\Support\StoreUrl::home($page) }} · {{ $page->theme->label() }} template ·
                            @if ($page->isPublished())
                                <span class="font-semibold text-ok-text">Live</span>
                                @if ($page->published_at)
                                    since {{ $page->published_at->format('M j') }}
                                @endif
                            @else
                                <span class="font-semibold text-warn-text">Draft</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ \App\Shared\Support\StoreUrl::forSeller($page) }}" target="_blank" rel="noopener"
                           class="px-4 py-2.5 rounded-xl bg-brand hover:bg-brand-hover text-white font-semibold text-xs">
                            {{ $page->isPublished() ? 'View store' : 'Preview store' }}
                        </a>

                        @if ($page->isPublished())
                            <form method="POST" action="{{ route('page.unpublish') }}"
                                  onsubmit="return confirm('Take {{ $page->name }} off the internet? Buyers will not be able to find it until you publish again.')">
                                @csrf
                                <button class="px-4 py-2.5 rounded-xl border border-rule text-ink-2 hover:bg-surface font-semibold text-xs">
                                    Unpublish
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('page.publish') }}">
                                @csrf
                                <button class="px-4 py-2.5 rounded-xl bg-ink hover:bg-slate-800 text-white font-semibold text-xs">
                                    Publish
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-card rounded-2xl border border-rule p-6">
                <h3 class="font-display font-bold text-ink mb-4">
                    {{ $page->isPublished() ? 'You are live' : 'Get started' }}
                </h3>

                <ul class="space-y-3">
                    @foreach ($checks as $check)
                        <li class="flex items-start gap-3">
                            <span @class([
                                'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                'bg-ok-wash text-ok-text' => $check['done'],
                                'bg-warn-wash text-warn-text' => ! $check['done'],
                            ])>
                                {{ $check['done'] ? '✓' : '!' }}
                            </span>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">{{ $check['label'] }}</p>
                                <p class="text-xs text-ink-3">{{ $check['hint'] }}</p>
                            </div>

                            @if (! $check['done'] && $check['url'])
                                <a href="{{ $check['url'] }}" class="ml-auto shrink-0 text-xs font-semibold text-brand hover:text-brand-text">
                                    Fix this
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="bg-card rounded-2xl border border-rule p-6 text-ink-2 text-sm">No page yet — <a href="{{ route('onboarding.show', 'business') }}" class="text-brand font-semibold">create yours</a>.</div>
        @endif
    </div>
@endsection