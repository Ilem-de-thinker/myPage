<x-admin.layout title="Overview">
  @php
      $cards = [
          ['Gross sales', \App\Shared\Support\Money::format($metrics['gmv_kobo']), 'Money that settled in sellers’ own accounts'],
          ['Paid orders', $metrics['paid_orders'], 'Of '.$metrics['orders'].' orders'],
          ['Stores', $metrics['stores'], ($metrics['stores_by_theme']['store'] ?? 0).' shop · '.($metrics['stores_by_theme']['service'] ?? 0).' booking'],
          ['Paid subscriptions', $metrics['active_subscriptions'], 'Of '.$metrics['sellers'].' sellers'],
      ];
  @endphp

  <h1 class="font-display text-2xl font-extrabold tracking-tight">Overview</h1>

  <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($cards as [$label, $value, $hint])
      <div class="rounded-2xl border border-rule bg-card p-5">
        <p class="text-[11px] uppercase tracking-wide text-ink-3">{{ $label }}</p>
        <p class="mt-1 font-display text-2xl font-extrabold tabular-naira">{{ $value }}</p>
        <p class="mt-1 text-[11px] text-ink-3">{{ $hint }}</p>
      </div>
    @endforeach
  </div>

  <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="rounded-2xl border border-rule bg-card p-5">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Orders</p>
      <p class="mt-2 font-display text-xl font-bold">
        {{ $metrics['paid_orders'] }} <span class="text-sm font-medium text-ink-3">of {{ $metrics['orders'] }} paid</span>
      </p>
    </div>

    <div class="rounded-2xl border border-rule bg-card p-5">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Needs attention</p>
      <p class="mt-2 font-display text-xl font-bold">
        {{ $metrics['suspended_stores'] }} {{ \Illuminate\Support\Str::plural('store', $metrics['suspended_stores']) }} suspended
      </p>
      <a href="{{ route('admin.stores') }}" class="mt-1 inline-block text-[11px] font-semibold text-brand-text hover:underline">Review stores</a>
    </div>
  </div>

  <div class="mt-6 rounded-2xl border border-rule bg-card p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-[11px] uppercase tracking-wide text-ink-3">AI</p>
        <p class="mt-2 text-sm font-semibold">
          {{ $ai['enabled'] ? 'Enabled' : 'Disabled' }}
          <span class="text-ink-3">·</span>
          {{ $ai['provider'] }} / {{ $ai['model'] }}
          <span class="text-ink-3">·</span>
          {{ $ai['configured'] ? 'Key configured' : 'No key configured' }}
        </p>
      </div>
      @if (auth()->user()->isCoreAdmin())
        <a href="{{ route('admin.ai-settings') }}" class="text-xs font-semibold text-brand-text hover:underline">Manage AI settings</a>
      @endif
    </div>
  </div>
</x-admin.layout>
