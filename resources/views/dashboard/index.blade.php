@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="Dashboard" :subtitle="'Welcome back, '.auth()->user()->name.'.'" />

        @if (($merchantStatus ?? null) === \App\Domains\Billing\Enums\MerchantStatus::Grace)
            <div class="rounded-2xl border border-warn-edge bg-warn-wash p-5">
                <p class="text-sm font-semibold text-warn-text">Your payment did not go through — you are in a grace period.</p>
                <p class="mt-1 text-xs text-warn-text">
                    You still have full access for
                    {{ $subscription->daysLeft() ?? 0 }} {{ \Illuminate\Support\Str::plural('day', $subscription->daysLeft() ?? 1) }}.
                    <a href="{{ route('billing.index') }}" class="font-semibold underline">Update your payment</a> before then.
                </p>
            </div>
        @elseif (($merchantStatus ?? null)?->isLapsed())
            <div class="rounded-2xl border border-danger-edge bg-danger-wash p-5">
                <p class="text-sm font-semibold text-danger-text">{{ $merchantStatus->label() }}: your shop is read-only.</p>
                <p class="mt-1 text-xs text-danger-text">
                    {{ $merchantStatus->description() }}
                    <a href="{{ route('billing.index') }}" class="font-semibold underline">Renew your plan</a> to reopen it.
                </p>
            </div>
        @endif

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

            <section x-data="analyticsInsights({digest: @jsExpr(route('dashboard.analytics.digest')), ask: @jsExpr(route('dashboard.analytics.ask')), csrf: @jsExpr(csrf_token())})" class="rounded-2xl border border-rule bg-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-3">Storefront analytics · last 7 days</p>
                        <h2 class="mt-1 text-xl font-display font-bold text-ink">Know what is getting attention</h2>
                        <p class="mt-1 text-xs text-ink-2">{{ number_format($analytics['views']) }} visits from {{ number_format($analytics['unique_visitors']) }} unique visitors.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('dashboard.analytics.index') }}" class="min-h-11 rounded-xl border border-rule-strong px-4 py-2.5 text-xs font-bold text-ink-2 hover:bg-surface">Open analytics</a>
                        <button type="button" @click="generate" :disabled="loading" class="min-h-11 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-brand-ink disabled:opacity-50" x-text="loading ? 'Preparing…' : 'Use Mercho summary'"></button>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <div>
                        <div class="flex h-32 items-end gap-2 rounded-xl bg-surface px-3 pb-3 pt-5">
                            @php $maxViews = max(1, collect($analytics['daily'])->max('views')); @endphp
                            @forelse ($analytics['daily'] as $day)
                                <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-1" title="{{ $day['day'] }}: {{ $day['views'] }} visits">
                                    <span class="text-[10px] font-semibold text-ink-3">{{ $day['views'] }}</span>
                                    <div class="w-full max-w-8 rounded-t-md bg-brand" style="height: {{ max(8, (int) round(($day['views'] / $maxViews) * 82)) }}px"></div>
                                    <span class="text-[9px] text-ink-3">{{ \Illuminate\Support\Carbon::parse($day['day'])->format('D') }}</span>
                                </div>
                            @empty
                                <p class="self-center text-xs text-ink-3">Traffic will appear here as buyers visit your storefront.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-rule p-3"><p class="text-[11px] text-ink-3">Listing views</p><p class="mt-1 text-lg font-bold text-ink">{{ number_format($analytics['product_views'] + $analytics['service_views']) }}</p></div>
                        <div class="rounded-xl border border-rule p-3"><p class="text-[11px] text-ink-3">WhatsApp taps</p><p class="mt-1 text-lg font-bold text-ink">{{ number_format($analytics['whatsapp_taps']) }}</p></div>
                        <div class="rounded-xl border border-rule p-3"><p class="text-[11px] text-ink-3">Enquiries</p><p class="mt-1 text-lg font-bold text-ink">{{ number_format($analytics['enquiries']) }}</p></div>
                        <div class="rounded-xl border border-rule p-3"><p class="text-[11px] text-ink-3">Top interest</p><p class="mt-1 truncate text-sm font-bold text-ink">{{ $analytics['top_products'][0]['name'] ?? ($analytics['top_services'][0]['name'] ?? 'No listing data yet') }}</p></div>
                    </div>
                </div>

                <div x-show="digest" x-cloak class="mt-4 rounded-xl bg-surface p-4 text-sm text-ink">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">Mercho summary</p>
                    <p x-text="digest?.summary || digest?.error"></p>
                    <ul x-show="digest?.recommendations?.length" class="mt-2 list-disc space-y-1 pl-5 text-xs text-ink-2"><template x-for="recommendation in (digest?.recommendations || [])" :key="recommendation"><li x-text="recommendation"></li></template></ul>
                </div>

                <form @submit.prevent="ask" class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <label class="sr-only" for="dashboard-analytics-question">Ask about your analytics</label>
                    <input id="dashboard-analytics-question" x-model="question" maxlength="1000" class="min-h-11 min-w-0 flex-1 rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink" placeholder="Ask about your store data">
                    <button class="min-h-11 rounded-xl border border-rule-strong px-4 text-xs font-bold text-ink-2 hover:bg-surface" :disabled="loading">Ask your data</button>
                </form>
                <p x-show="answer" x-cloak x-text="answer?.answer || answer?.error" class="mt-3 rounded-xl bg-surface p-3 text-xs text-ink-2"></p>
            </section>

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
