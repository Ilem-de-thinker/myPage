@extends('layouts.app')

@section('content')
    <x-ui.page-header
        :title="$order->reference"
        :subtitle="$order->source->label()"
        :back="route('dashboard.orders.index', ['source' => $order->source->value])"
        back-label="Back to orders"
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">Status</p>
                    <span class="mt-1 inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                        {{ $order->status->label() }}
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">Total</p>
                    <p class="mt-1 text-xl font-extrabold text-ink">{{ \App\Shared\Support\Money::format($order->total_kobo) }}</p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 text-xs">
                <div>
                    <dt class="text-slate-400">Placed</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->created_at->format('M d, Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Source</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->source->label() }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Customer</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Phone</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_phone ?: '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-slate-400">Email</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_email ?: '—' }}</dd>
                </div>
                @if ($order->customer_note)
                    <div class="col-span-2">
                        <dt class="text-slate-400">Note</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_note }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-slate-400 mb-3">Items</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($order->items as $item)
                    <li class="flex items-center justify-between gap-4 py-2.5 text-sm">
                        <div>
                            <p class="font-semibold text-ink">{{ $item->quantity }} x {{ $item->name }}</p>
                            @if ($item->sku)
                                <p class="text-[11px] text-slate-400">{{ $item->sku }}</p>
                            @endif
                        </div>
                        <p class="font-bold text-ink">{{ \App\Shared\Support\Money::format($item->line_kobo) }}</p>
                    </li>
                @endforeach
            </ul>
        </div>

        @php $next = $order->status->allowedTransitions(); @endphp

        @if ($next)
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-slate-400 mb-3">Move to</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($next as $status)
                        <form method="POST" action="{{ route('dashboard.orders.status', $order) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $status->value }}">
                            <button class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-dark">
                                {{ $status->label() }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($order->history->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-slate-400 mb-3">History</h2>
                <ul class="space-y-2 text-xs">
                    @foreach ($order->history as $entry)
                        <li class="flex items-center justify-between gap-4">
                            <span class="text-slate-600">
                                {{ $entry->from_status?->label() ?? 'Created' }} &rarr; <span class="font-semibold text-ink">{{ $entry->to_status->label() }}</span>
                                @if ($entry->note)
                                    <span class="text-slate-400">&mdash; {{ $entry->note }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-slate-400">{{ $entry->created_at->format('M d, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection