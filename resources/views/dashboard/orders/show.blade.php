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
        <div class="bg-card rounded-2xl border border-rule p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] uppercase tracking-wide text-ink-3">Status</p>
                    <span class="mt-1 inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                        {{ $order->status->label() }}
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-[11px] uppercase tracking-wide text-ink-3">Total</p>
                    <p class="mt-1 text-xl font-extrabold text-ink">{{ \App\Shared\Support\Money::format($order->total_kobo) }}</p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-rule pt-5 text-xs">
                <div>
                    <dt class="text-ink-3">Placed</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->created_at->format('M d, Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-ink-3">Source</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->source->label() }}</dd>
                </div>
                <div>
                    <dt class="text-ink-3">Customer</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-3">Phone</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_phone ?: '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-ink-3">Email</dt>
                    <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_email ?: '—' }}</dd>
                </div>
                @if ($order->customer_note)
                    <div class="col-span-2">
                        <dt class="text-ink-3">Note</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $order->customer_note }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">Items</h2>
            <ul class="divide-y divide-rule">
                @foreach ($order->items as $item)
                    <li class="flex items-center justify-between gap-4 py-2.5 text-sm">
                        <div>
                            <p class="font-semibold text-ink">{{ $item->quantity }} x {{ $item->name }}</p>
                            @if ($item->sku)
                                <p class="text-[11px] text-ink-3">{{ $item->sku }}</p>
                            @endif
                        </div>
                        <p class="font-bold text-ink">{{ \App\Shared\Support\Money::format($item->line_kobo) }}</p>
                    </li>
                @endforeach
            </ul>
        </div>

        @php
            $next = collect($order->status->allowedTransitions())
                ->reject(fn ($status) => $order->status === \App\Domains\Orders\Enums\OrderStatus::PendingPayment
                    && $status === \App\Domains\Orders\Enums\OrderStatus::Paid)
                ->all();
        @endphp

        @if ($next)
            <div class="bg-card rounded-2xl border border-rule p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">Move to</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($next as $status)
                        <form method="POST" action="{{ route('dashboard.orders.status', $order) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $status->value }}">
                            <button class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-hover">
                                {{ $status->label() }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($order->history->isNotEmpty())
            <div class="bg-card rounded-2xl border border-rule p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">History</h2>
                <ul class="space-y-2 text-xs">
                    @foreach ($order->history as $entry)
                        <li class="flex items-center justify-between gap-4">
                            <span class="text-ink-2">
                                {{ $entry->from_status?->label() ?? 'Created' }} &rarr; <span class="font-semibold text-ink">{{ $entry->to_status->label() }}</span>
                                @if ($entry->note)
                                    <span class="text-ink-3">&mdash; {{ $entry->note }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-ink-3">{{ $entry->created_at->format('M d, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection