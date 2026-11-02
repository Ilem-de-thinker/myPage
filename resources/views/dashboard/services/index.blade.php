@extends('layouts.app')

@section('content')
    <x-ui.page-header title="Services" subtitle="What customers can book, and for how long.">
        <div class="flex flex-wrap items-center gap-2"><button type="button" @click.stop="$dispatch('ai-open', { text: '' })" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-brand-edge bg-brand-wash px-4 py-2.5 text-xs font-semibold text-brand-text"><x-ui.icon name="spark" class="size-4" /> Use Mercho</button><a href="{{ route('services.create') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover"><x-ui.icon name="briefcase" class="size-4" /> Add a service</a></div>
    </x-ui.page-header>

    @if ($daysOpen === 0)
        <div class="mb-4 rounded-2xl border border-warn-edge bg-warn-wash p-5">
            <p class="text-sm font-semibold text-warn-text">You have no opening hours set.</p>
            <p class="mt-1 text-xs text-warn-text">
                Customers cannot book anything until you say when you are open. Set your hours before
                sharing the page.
            </p>
        </div>
    @endif

    <div class="bg-card rounded-2xl border border-rule overflow-hidden">
        @forelse ($services as $service)
            <div class="flex flex-wrap items-center gap-3 border-b border-rule px-4 py-3 last:border-0 hover:bg-surface/60">
                @if ($service->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($service->image_path) }}"
                         alt="{{ $service->name }}" class="h-12 w-12 shrink-0 rounded-xl object-cover">
                @else
                    {{-- No photo yet: the initial stands in, so the row is not a
                         line of text waiting for an image. --}}
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-wash text-sm font-bold text-brand-text" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($service->name, 0, 1)) }}</span>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-ink">{{ $service->name }}</p>
                        @unless ($service->is_active)
                            <span class="rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold text-ink-3">Hidden</span>
                        @endunless
                        @if ($service->isOnSale())
                            <span class="rounded-full bg-brand-wash px-2 py-0.5 text-[10px] font-semibold text-brand-text">Sale −{{ $service->discountPercent() }}%</span>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-xs text-ink-3">
                        {{ $service->durationLabel() }}
                        @if ($service->buffer_min > 0)
                            &middot; {{ $service->buffer_min }}m reset after
                        @endif
                        &middot; {{ $service->bookings_count }} {{ \Illuminate\Support\Str::plural('booking', $service->bookings_count) }}
                    </p>
                </div>

                <div class="text-right">
                    <div class="flex items-baseline justify-end gap-1.5">
                        <p class="text-sm font-bold text-ink">{{ $service->priceLabel() }}</p>
                        @if ($service->isOnSale())
                            <del class="text-[11px] text-ink-3 tabular-nums">{{ \App\Shared\Support\Money::format($service->originalPriceKobo()) }}</del>
                        @endif
                    </div>
                    @if ($service->hasDeposit())
                        <p class="text-[11px] text-ink-3">{{ \App\Shared\Support\Money::format($service->amountDueKobo()) }} to book</p>
                    @endif
                </div>

                <a href="{{ route('services.edit', $service) }}" class="text-xs font-semibold text-brand">Edit</a>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm text-ink-3 mb-1">No services yet.</p>
                <p class="text-xs text-ink-3">
                    Add what you offer, how long it takes and what it costs. Customers book straight off this list.
                </p>
            </div>
        @endforelse
    </div>

    @if ($services->hasPages())
        <div class="mt-4">{{ $services->links() }}</div>
    @endif
@endsection
