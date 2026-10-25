<x-admin.layout title="Analytics">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-3">Platform intelligence</p>
      <h1 class="mt-1 font-display text-2xl font-extrabold tracking-tight">Analytics</h1>
      <p class="mt-1 text-sm text-ink-2">Traffic, discovery, orders and bookings across the platform.</p>
    </div>
    <nav class="flex gap-2" aria-label="Analytics period">
      @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $period => $label)
        <a href="{{ route('admin.analytics', ['days' => $period]) }}" @class(['rounded-lg px-3 py-2 text-xs font-bold', 'bg-brand text-brand-ink' => $days === $period, 'border border-rule bg-card text-ink-2' => $days !== $period])>{{ $label }}</a>
      @endforeach
    </nav>
  </div>

  <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ([['Views', 'views'], ['Visitors', 'unique_visitors'], ['Listing views', 'listing_views'], ['Directory clicks', 'directory_clicks'], ['Paid orders', 'paid_orders'], ['Bookings', 'bookings']] as [$label, $key])
      <div class="rounded-2xl border border-rule bg-card p-4"><p class="text-[11px] font-semibold text-ink-3">{{ $label }}</p><p class="mt-2 font-display text-2xl font-black text-ink">{{ number_format($metrics[$key]) }}</p></div>
    @endforeach
  </div>

  <div class="mt-6 grid gap-4 lg:grid-cols-[1.5fr_1fr]">
    <section class="rounded-2xl border border-rule bg-card p-5">
      <div class="flex items-center justify-between gap-3"><div><h2 class="text-sm font-bold text-ink">Activity by day</h2><p class="mt-1 text-xs text-ink-3">All recorded public events.</p></div><span class="text-sm font-bold text-ink">{{ \App\Shared\Support\Money::format($metrics['revenue_kobo']) }} paid</span></div>
      <div class="mt-6 grid min-h-44 grid-cols-7 items-end gap-2" aria-label="Platform activity by day">
        @php($max = max(1, collect($metrics['daily'])->max('events')))
        @foreach ($metrics['daily'] as $day)
          <div class="text-center"><div class="mx-auto max-w-8 rounded-t bg-brand" style="height: {{ max(8, min(160, ($day['events'] / $max) * 160)) }}px"></div><p class="mt-2 text-[10px] text-ink-3">{{ $day['label'] }}</p></div>
        @endforeach
      </div>
    </section>
    <section class="rounded-2xl border border-rule bg-card p-5"><h2 class="text-sm font-bold text-ink">Top traffic sources</h2><div class="mt-4 space-y-3">@forelse ($metrics['top_sources'] as $source => $count)<div class="flex items-center justify-between gap-3 text-xs"><span class="truncate text-ink-2">{{ $source }}</span><span class="font-bold text-ink">{{ number_format($count) }}</span></div>@empty<p class="text-xs text-ink-3">No traffic recorded yet.</p>@endforelse</div></section>
  </div>
</x-admin.layout>
