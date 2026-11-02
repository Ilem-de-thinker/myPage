@extends('layouts.app')

@section('content')
    <div class="mx-auto w-full px-4 py-6 sm:px-6 md:max-w-[640px] lg:max-w-[1040px] lg:px-10 lg:py-10">
        <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:justify-between lg:mb-10">
            <div>
                <h1 class="text-[18px] font-medium text-ink lg:text-[24px]">Insights</h1>
                <p class="mt-1 text-[12px] text-ink-3">Privacy-respecting</p>
            </div>
            <div class="flex w-full rounded-[10px] bg-surface p-[3px] sm:w-auto">
                @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $period => $label)
                    <a href="{{ route('dashboard.analytics.index', ['days' => $period]) }}" class="flex min-h-[44px] flex-1 items-center justify-center rounded-[7px] px-4 text-[13px] font-medium transition sm:min-h-[32px] sm:flex-none sm:px-5 {{ $days === $period ? 'bg-card text-ink shadow-[0_0_0_0.5px_var(--rule)]' : 'text-ink-3 hover:text-ink-2' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @php
            $whats = [];
            if (($metrics['views'] ?? 0) > 0) { $whats[] = ['Views', $metrics['views'], number_format($metrics['views']), null]; }
            if (($metrics['unique_visitors'] ?? 0) > 0) { $whats[] = ['Visitors', $metrics['unique_visitors'], number_format($metrics['unique_visitors']), null]; }
            if (($metrics['paid_orders'] ?? 0) > 0) { $whats[] = ['Orders', $metrics['paid_orders'], number_format($metrics['paid_orders']).' paid', route('dashboard.orders.index')]; }
            if (($metrics['confirmed_bookings'] ?? 0) > 0) { $whats[] = ['Bookings', $metrics['confirmed_bookings'], number_format($metrics['confirmed_bookings']).' confirmed', route('bookings.index')]; }
            $maxWhat = max(1, collect($whats)->max(fn ($r) => $r[1]) ?? 1);
            $hasActivity = count($whats) > 0;
            $chips = [];
            if (($metrics['whatsapp_taps'] ?? 0) === 0) { $chips[] = 'WhatsApp taps'; }
            if (($metrics['enquiries'] ?? 0) === 0) { $chips[] = 'Enquiries'; }
            if (($metrics['confirmed_bookings'] ?? 0) === 0) { $chips[] = 'Bookings'; }
            if (($metrics['product_views'] ?? 0) === 0) { $chips[] = 'Product views'; }
            if (($metrics['service_views'] ?? 0) === 0) { $chips[] = 'Service views'; }
        @endphp

        <div class="lg:grid lg:grid-cols-[45%_1fr] lg:gap-16">
            <div class="lg:border-r lg:border-rule/50 lg:pr-8">
                <div class="space-y-2">
                    <p class="text-[12px] text-ink-3">Revenue, last {{ $days }} days</p>
                    <p class="text-[34px] font-medium leading-none text-ink lg:text-[48px]">{{ \App\Shared\Support\Money::format($metrics['revenue_kobo']) }}</p>
                    <p class="text-[14px] text-ink-3">{{ number_format($metrics['paid_orders']) }} paid orders · <span class="text-ok-text">{{ $metrics['conversion_rate'] }}% conversion</span></p>
                </div>
                @if (! $hasActivity)
                    <p class="mt-6 text-[14px] text-ink-3">No activity yet. Share your store link to get your first visitors.</p>
                @endif
            </div>
            <div class="mt-8 lg:mt-0">
                @if ($hasActivity)
                    <p class="mb-2 text-[12px] text-ink-3">What's happening</p>
                    <div>
                        @foreach ($whats as [$label, $value, $valueText, $link])
                            @php($width = max(4, round(($value / $maxWhat) * 100)))
                            @if ($link)
                                <a href="{{ $link }}" class="group flex items-center gap-3 border-t border-rule/50 py-[13px] transition hover:bg-surface first:border-t-0">
                            @else
                                <div class="flex items-center gap-3 border-t border-rule/50 py-[13px] first:border-t-0">
                            @endif
                                <span class="w-20 shrink-0 text-[14px] text-ink">{{ $label }}</span>
                                <div class="h-[6px] flex-1 rounded-full bg-surface">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ $width }}%"></div>
                                </div>
                                <span class="w-24 shrink-0 text-right text-[15px] font-medium text-ink">{{ $valueText }}</span>
                                @if ($link)
                                    <svg class="size-3.5 shrink-0 text-ink-3 opacity-0 transition group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                @endif
                            @if ($link)
                                </a>
                            @else
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @if (count($chips) > 0)
            <div class="mt-6 border-t border-rule/50 pt-6 lg:mt-8 lg:pt-8">
                <p class="mb-3 text-[12px] text-ink-3">No activity yet</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($chips as $chip)
                        <span class="inline-flex items-center gap-[6px] rounded-full border border-rule px-[11px] py-[5px] text-[12px] text-ink-2">
                            {{ $chip }} <span class="text-ink-3">0</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-6">
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Traffic sources</h2><div class="mt-4 space-y-2">@forelse ($metrics['sources'] as $source => $count)<div class="flex justify-between gap-3 text-xs"><span class="truncate text-ink-2">{{ $source }}</span><span class="font-bold text-ink">{{ number_format($count) }}</span></div>@empty<p class="text-xs text-ink-3">No source data yet.</p>@endforelse</div></div>
                <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Device mix</h2><div class="mt-4 flex flex-wrap gap-2">@forelse ($metrics['devices'] as $device => $count)<span class="rounded-full border border-rule px-3 py-1 text-xs text-ink-2">{{ ucfirst($device) }} · {{ number_format($count) }}</span>@empty<p class="text-xs text-ink-3">No device data yet.</p>@endforelse</div></div>
            </section>
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Most-viewed products</h2><div class="mt-3 space-y-2">@forelse ($metrics['top_products'] ?? [] as $item)<div class="flex items-center justify-between gap-3 text-xs"><span class="truncate text-ink">{{ $item['name'] }}</span><span class="font-bold text-ink-2">{{ $item['views'] }}</span></div>@empty<p class="text-xs text-ink-3">No product views yet.</p>@endforelse</div></div>
                <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Most-viewed services</h2><div class="mt-3 space-y-2">@forelse ($metrics['top_services'] ?? [] as $item)<div class="flex items-center justify-between gap-3 text-xs"><span class="truncate text-ink">{{ $item['name'] }}</span><span class="font-bold text-ink-2">{{ $item['views'] }}</span></div>@empty<p class="text-xs text-ink-3">No service views yet.</p>@endforelse</div></div>
            </section>
            <section class="rounded-2xl border border-rule bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-sm font-bold text-ink">Weekly Mercho digest</h2><p class="mt-1 text-xs text-ink-3">The numbers are computed by the platform; Mercho only narrates them.</p></div><button type="button" @click="generate" :disabled="loading" class="min-h-11 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50" x-text="loading ? 'Preparing…' : 'Generate digest'"></button></div>
                <pre x-show="digest" x-cloak x-text="format(digest)" class="mt-4 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-surface p-4 text-xs leading-5 text-ink"></pre>
            </section>
            <section class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Ask your data</h2><p class="mt-1 text-xs text-ink-3">Answers use only this store’s computed analytics.</p><form @submit.prevent="ask" class="mt-4 flex flex-col gap-2 sm:flex-row"><label class="sr-only" for="analytics-question">Analytics question</label><input id="analytics-question" x-model="question" maxlength="1000" class="min-h-11 min-w-0 flex-1 rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink" placeholder="Which products attracted the most interest?"><button class="min-h-11 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink" :disabled="loading">Ask</button></form><pre x-show="answer" x-cloak x-text="format(answer)" class="mt-4 whitespace-pre-wrap rounded-xl bg-surface p-4 text-xs leading-5 text-ink"></pre></section>
        </div>
    </div>
@endsection
