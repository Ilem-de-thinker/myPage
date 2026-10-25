@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Insights" subtitle="A privacy-respecting view of your storefront, listings and customer intent."><div class="flex flex-wrap gap-2"><div class="flex rounded-xl border border-rule bg-card p-1">@foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $period => $label)<a href="{{ route('dashboard.analytics.index', ['days' => $period]) }}" @class(['rounded-lg px-3 py-2 text-xs font-bold', 'bg-brand text-brand-ink' => $days === $period, 'text-ink-2' => $days !== $period])>{{ $label }}</a>@endforeach</div><button type="button" @click="$dispatch('ai.open', { text: '' })" class="min-h-11 rounded-xl border border-brand-edge bg-brand-wash px-4 text-xs font-bold text-brand-text">Use AI</button></div></x-ui.page-header>
    <div x-data="analyticsInsights({digest: @jsExpr(route('dashboard.analytics.digest', ['days' => $days])), ask: @jsExpr(route('dashboard.analytics.ask')), csrf: @jsExpr(csrf_token())})" class="mx-auto max-w-5xl space-y-6">
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach (['views' => 'Views', 'unique_visitors' => 'Visitors', 'product_views' => 'Product views', 'service_views' => 'Service views', 'whatsapp_taps' => 'WhatsApp taps', 'enquiries' => 'Enquiries'] as $key => $label)
                <div class="rounded-2xl border border-rule bg-card p-4"><p class="text-[11px] font-semibold text-ink-3">{{ $label }}</p><p class="mt-2 text-2xl font-black text-ink">{{ number_format($metrics[$key]) }}</p></div>
            @endforeach
        </section>
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([['Orders', number_format($metrics['paid_orders']).' paid'], ['Bookings', number_format($metrics['confirmed_bookings']).' confirmed'], ['Revenue', \App\Shared\Support\Money::format($metrics['revenue_kobo'])], ['Conversion', $metrics['conversion_rate'].'%']] as [$label, $value])
                <div class="rounded-2xl border border-rule bg-card p-4"><p class="text-[11px] font-semibold text-ink-3">{{ $label }}</p><p class="mt-2 text-lg font-black text-ink">{{ $value }}</p></div>
            @endforeach
        </section>
        <section class="rounded-2xl border border-brand-edge bg-brand-wash p-5">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-[11px] font-bold uppercase tracking-wide text-brand-text">MyPage directory traffic</p><h2 class="mt-1 text-xl font-black text-ink">{{ number_format($metrics['directory_clicks'] ?? 0) }} visits in the last {{ $days }} days</h2><p class="mt-1 text-xs text-ink-2">People who discovered your store, product or service on mypage.ng.</p></div><x-ui.icon name="chart" class="size-7 text-brand-text" /></div>
            @if (!empty($metrics['directory_sources']))<div class="mt-4 flex flex-wrap gap-2">@foreach ($metrics['directory_sources'] as $source => $count)<span class="rounded-full border border-brand-edge bg-card px-3 py-1 text-[11px] font-semibold text-ink">{{ $source }} · {{ $count }}</span>@endforeach</div>@endif
            @if (!empty($metrics['directory_queries']))<div class="mt-4"><p class="text-[11px] font-bold text-ink-2">Top searches</p><div class="mt-2 flex flex-wrap gap-2">@foreach ($metrics['directory_queries'] as $query => $count)<span class="rounded-full border border-brand-edge bg-card px-3 py-1 text-[11px] font-semibold text-ink">{{ $query }} · {{ $count }}</span>@endforeach</div></div>@endif
            @if (!empty($metrics['daily']))
                <div class="mt-5 overflow-x-auto pb-2" aria-label="Storefront activity by day">
                    <div class="flex min-w-full items-end gap-1.5">
                        @php($maxViews = max(1, collect($metrics['daily'])->max('views')))
                        @foreach ($metrics['daily'] as $day)
                            <div class="flex min-w-8 flex-1 flex-col items-center justify-end" title="{{ $day['day'] }}: {{ $day['views'] }} views, {{ $day['whatsapp_taps'] }} WhatsApp taps">
                                <div class="w-full max-w-7 rounded-t bg-brand-wash-2" style="height: {{ max(8, min(96, (($day['views'] ?? 0) / $maxViews) * 96)) }}px"></div>
                                <p class="mt-1 whitespace-nowrap text-[10px] text-ink-3">{{ $day['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Traffic sources</h2><div class="mt-4 space-y-2">@forelse ($metrics['sources'] as $source => $count)<div class="flex justify-between gap-3 text-xs"><span class="truncate text-ink-2">{{ $source }}</span><span class="font-bold text-ink">{{ number_format($count) }}</span></div>@empty<p class="text-xs text-ink-3">No source data yet.</p>@endforelse</div></div>
            <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Device mix</h2><div class="mt-4 flex flex-wrap gap-2">@forelse ($metrics['devices'] as $device => $count)<span class="rounded-full border border-rule px-3 py-1 text-xs text-ink-2">{{ ucfirst($device) }} · {{ number_format($count) }}</span>@empty<p class="text-xs text-ink-3">No device data yet.</p>@endforelse</div></div>
        </section>
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Most-viewed products</h2><div class="mt-3 space-y-2">@forelse ($metrics['top_products'] ?? [] as $item)<div class="flex items-center justify-between gap-3 text-xs"><span class="truncate text-ink">{{ $item['name'] }}</span><span class="font-bold text-ink-2">{{ $item['views'] }}</span></div>@empty<p class="text-xs text-ink-3">No product views yet.</p>@endforelse</div></div>
            <div class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Most-viewed services</h2><div class="mt-3 space-y-2">@forelse ($metrics['top_services'] ?? [] as $item)<div class="flex items-center justify-between gap-3 text-xs"><span class="truncate text-ink">{{ $item['name'] }}</span><span class="font-bold text-ink-2">{{ $item['views'] }}</span></div>@empty<p class="text-xs text-ink-3">No service views yet.</p>@endforelse</div></div>
        </section>
        <section class="rounded-2xl border border-rule bg-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-sm font-bold text-ink">Weekly AI digest</h2><p class="mt-1 text-xs text-ink-3">The numbers are computed by the platform; AI only narrates them.</p></div><button type="button" @click="generate" :disabled="loading" class="min-h-11 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50" x-text="loading ? 'Preparing…' : 'Generate digest'"></button></div>
            <pre x-show="digest" x-cloak x-text="format(digest)" class="mt-4 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-surface p-4 text-xs leading-5 text-ink"></pre>
        </section>
        <section class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Ask your data</h2><p class="mt-1 text-xs text-ink-3">Answers use only this store’s computed analytics.</p><form @submit.prevent="ask" class="mt-4 flex flex-col gap-2 sm:flex-row"><label class="sr-only" for="analytics-question">Analytics question</label><input id="analytics-question" x-model="question" maxlength="1000" class="min-h-11 min-w-0 flex-1 rounded-xl border border-rule-strong bg-card px-3 text-sm text-ink" placeholder="Which products attracted the most interest?"><button class="min-h-11 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink" :disabled="loading">Ask</button></form><pre x-show="answer" x-cloak x-text="format(answer)" class="mt-4 whitespace-pre-wrap rounded-xl bg-surface p-4 text-xs leading-5 text-ink"></pre></section>
    </div>
@endsection
