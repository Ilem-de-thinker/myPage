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

        @php $next = $order->status->allowedTransitions(); @endphp

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

        @if ($order->escrow)
            <div class="bg-card rounded-2xl border border-rule p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">Escrow</h2>
                        <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $order->escrow->status->badgeClasses() }}">
                            {{ $order->escrow->status->label() }}
                        </span>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] uppercase tracking-wide text-ink-3">Held</p>
                        <p class="mt-1 text-lg font-extrabold text-ink">{{ \App\Shared\Support\Money::format($order->escrow->amount_kobo) }}</p>
                    </div>
                </div>

                <p class="mt-4 text-xs text-ink-3">
                    This buyer's money is held by {{ config('app.name') }} instead of your wallet.
                    @if ($order->escrow->auto_releases_at && $order->escrow->isHeld())
                        It releases itself {{ $order->escrow->auto_releases_at->diffForHumans() }}
                        unless you raise a dispute first.
                    @endif
                </p>

                @if ($order->escrow->dispute_reason)
                    <div class="mt-4 rounded-xl bg-warn-wash border border-warn-edge px-4 py-3 text-xs text-warn-text">
                        <p class="font-semibold">
                            Dispute
                            @if ($order->escrow->disputed_by_seller_id)
                                raised by you
                            @else
                                raised by the buyer
                            @endif
                        </p>
                        <p class="mt-1">{{ $order->escrow->dispute_reason }}</p>
                        @if ($order->escrow->status === \App\Domains\Billing\Models\EscrowStatus::Frozen)
                            <p class="mt-2">An admin decides where this goes. Neither of you can move it.</p>
                        @endif
                    </div>
                @endif

                @if ($order->escrow->isHeld())
                    <form method="POST" action="{{ route('dashboard.orders.escrow-dispute', $order) }}"
                          class="mt-5 border-t border-rule pt-4">
                        @csrf
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">Raise a dispute and freeze the money</span>
                            <textarea name="reason" rows="3" required minlength="20" maxlength="1000"
                                      class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2 text-sm"
                                      placeholder="The buyer says the second Ankara set was never delivered, but the courier marked it collected.">{{ old('reason') }}</textarea>
                            <span class="mt-1 block text-[11px] text-ink-3">
                                Freezing stops the payout. An admin then decides: you cannot undo this yourself.
                            </span>
                        </label>
                        @error('reason')
                            <p class="mt-2 text-xs font-semibold text-danger-text">{{ $message }}</p>
                        @enderror
                        <button class="mt-3 rounded-xl border border-danger-edge px-4 py-2.5 text-xs font-semibold text-danger-text hover:bg-danger-wash">
                            Freeze and escalate
                        </button>
                    </form>
                @endif

                @if ($order->escrow->isHeld())
                    <p class="mt-4 text-[11px] text-ink-3">
                        Share this link with the buyer so they can release early if they are happy:
                    </p>
                    <code class="mt-1 block break-all rounded-lg bg-surface px-3 py-2 text-[11px] text-ink-2">{{ route('storefront.escrow', ['slug' => $page->slug, 'token' => $order->escrow->releaseToken()]) }}</code>
                @endif
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