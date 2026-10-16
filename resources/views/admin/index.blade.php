<x-admin.layout title="Overview">
  @php
      $cards = [
          ['Gross sales', \App\Shared\Support\Money::format($metrics['gmv_kobo']), 'Money that actually settled'],
          ['Commission', \App\Shared\Support\Money::format($metrics['commission_kobo']), config('mypage.commission_bps').' bps of each sale'],
          ['Held in escrow', \App\Shared\Support\Money::format($metrics['escrow_held_kobo']), 'Buyer money not yet released'],
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

  <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-rule bg-card p-5">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Orders</p>
      <p class="mt-2 font-display text-xl font-bold">
        {{ $metrics['paid_orders'] }} <span class="text-sm font-medium text-ink-3">of {{ $metrics['orders'] }} paid</span>
      </p>
    </div>

    <div class="rounded-2xl border border-rule bg-card p-5">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Stores</p>
      <p class="mt-2 font-display text-xl font-bold">{{ $metrics['stores'] }}</p>
      <p class="mt-1 text-[11px] text-ink-3">
        {{ ($metrics['stores_by_theme']['store'] ?? 0) }} shop,
        {{ ($metrics['stores_by_theme']['service'] ?? 0) }} booking
      </p>
    </div>

    <div class="rounded-2xl border border-rule bg-card p-5">
      <p class="text-[11px] uppercase tracking-wide text-ink-3">Needs attention</p>
      <p class="mt-2 font-display text-xl font-bold">
        {{ $metrics['open_disputes'] }} {{ \Illuminate\Support\Str::plural('dispute', $metrics['open_disputes']) }}
      </p>
      @if ($metrics['suspended_stores'] > 0)
        <p class="mt-1 text-[11px] text-ink-3">{{ $metrics['suspended_stores'] }} suspended stores</p>
      @endif
    </div>
  </div>

  @if ($metrics['open_disputes'] > 0)
    <div class="mt-6 rounded-2xl border border-warn-edge bg-warn-wash p-5">
      <p class="text-sm font-semibold text-warn-text">
        {{ $metrics['open_disputes'] }} {{ \Illuminate\Support\Str::plural('escrow is', $metrics['open_disputes']) }} frozen.
      </p>
      <p class="mt-1 text-xs text-warn-text">Frozen money does not move until an admin decides. Nobody else can release it.</p>
      <a href="{{ route('admin.escrows', ['status' => 'frozen']) }}"
         class="mt-3 inline-block rounded-xl bg-amber-900 px-4 py-2 text-xs font-semibold text-white">
        Open the dispute queue
      </a>
    </div>
  @endif
</x-admin.layout>