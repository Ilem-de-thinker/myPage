@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Orders"
        :subtitle="$activeSource?->label() ?? 'Every order from WhatsApp and paid checkout.'"
    />

    <div class="mb-4 flex gap-2 border-b border-rule">
        @foreach (\App\Domains\Orders\Enums\OrderSource::cases() as $tab)
            @php $isActive = $activeSource === $tab; @endphp
            <a
                href="{{ route('dashboard.orders.index', ['source' => $tab->value]) }}"
                @class([
                    '-mb-px border-b-2 px-4 py-2.5 text-xs font-semibold transition',
                    'border-brand text-brand' => $isActive,
                    'border-transparent text-ink-3 hover:text-ink' => ! $isActive,
                ])
            >
                {{ $tab->shortLabel() }}
                <span class="ml-1 rounded-full bg-tint px-1.5 py-0.5 text-[10px] text-ink-2">
                    {{ \App\Domains\Orders\Models\Order::where('store_page_id', $page->id)->where('source', $tab->value)->count() }}
                </span>
            </a>
        @endforeach

        <a
            href="{{ route('dashboard.orders.index') }}"
            @class([
                '-mb-px ml-auto border-b-2 px-4 py-2.5 text-xs font-semibold transition',
                'border-transparent text-ink-3 hover:text-ink' => $activeSource !== null,
                'hidden' => $activeSource === null,
            ])
        >
            All
        </a>
    </div>

    <div class="bg-card rounded-2xl border border-rule overflow-hidden">
        @forelse ($orders as $order)
            <div class="flex flex-wrap items-center gap-3 border-b border-rule px-4 py-3 last:border-0 hover:bg-surface/60">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('dashboard.orders.show', $order) }}" class="text-sm font-semibold text-ink hover:text-brand">
                            {{ $order->reference }}
                        </a>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                        <span class="rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold text-ink-2">
                            {{ $order->source->shortLabel() }}
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-ink-3">
                        {{ $order->customer_name ?: 'Guest' }}
                        @if ($order->items->isNotEmpty())
                            &middot; {{ $order->items->pluck('name')->join(', ') }}
                        @endif
                    </p>
                </div>

                <p class="text-sm font-bold text-ink">{{ \App\Shared\Support\Money::format($order->total_kobo) }}</p>

                <a href="{{ route('dashboard.orders.show', $order) }}" class="text-xs font-semibold text-brand">View</a>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm text-ink-3 mb-1">No {{ $activeSource?->shortLabel() ?? '' }} orders yet.</p>
                <p class="text-xs text-ink-3">
                    @if ($activeSource === \App\Domains\Orders\Enums\OrderSource::Whatsapp)
                        Orders your buyers send over WhatsApp land here automatically.
                    @elseif ($activeSource === \App\Domains\Orders\Enums\OrderSource::Platform)
                        Orders paid for on your page land here automatically.
                    @else
                        Share your store link or your WhatsApp number to get your first order.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if ($orders->hasPages())
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
@endsection