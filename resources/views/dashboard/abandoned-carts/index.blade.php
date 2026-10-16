@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Abandoned carts"
        subtitle="Buyers who started a checkout and left. Each one is a sale you can still chase."
    />

    <div class="mb-4 flex gap-2 border-b border-rule">
        @foreach (\App\Domains\Orders\Enums\AbandonedCartStatus::cases() as $tab)
            @php $isActive = $activeStatus === $tab; @endphp
            <a
                href="{{ route('abandoned-carts.index', ['status' => $tab->value]) }}"
                @class([
                    '-mb-px border-b-2 px-4 py-2.5 text-xs font-semibold capitalize transition',
                    'border-brand text-brand' => $isActive,
                    'border-transparent text-ink-3 hover:text-ink' => ! $isActive,
                ])
            >
                {{ $tab->value }}
                @if ($count = $counts[$tab->value] ?? 0)
                    <span class="ml-1 rounded-full bg-tint px-1.5 py-0.5 text-[10px] text-ink-2">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($carts as $cart)
            <div class="bg-card rounded-2xl border border-rule p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">
                            {{ $cart->email ?: $cart->phone ?: 'No contact details' }}
                        </p>
                        <p class="mt-0.5 text-xs text-ink-3">
                            {{ collect($cart->cart)->map(fn ($line) => $line['quantity'].' x '.$line['name'])->join(', ') }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-sm font-bold text-ink">{{ \App\Shared\Support\Money::format($cart->total_kobo) }}</p>
                        <p class="text-[11px] text-ink-3">Left {{ $cart->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-rule pt-3">
                    @if ($cart->reminded_at)
                        <span class="text-[11px] text-ink-3">Reminder sent {{ $cart->reminded_at->diffForHumans() }}</span>
                    @else
                        <span class="text-[11px] text-ink-3">Not chased yet</span>
                    @endif

                    <div class="ml-auto flex items-center gap-3">
                        @if ($chase = \App\Shared\Support\WhatsApp::buyerLink($cart->phone, 'Hello, you left an order with us at '.$page->name.'. Would you like to finish it?'))
                            <a href="{{ $chase }}"
                               class="text-[11px] font-semibold text-brand hover:text-brand-text" target="_blank" rel="noopener">Nudge on WhatsApp</a>
                        @endif

                        @if ($activeStatus->isOpen())
                            <form method="POST" action="{{ route('abandoned-carts.destroy', $cart) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11px] font-semibold text-ink-3 hover:text-ink">Close</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-card rounded-2xl border border-rule px-6 py-16 text-center">
                <p class="text-sm text-ink-3 mb-1">
                    {{ $activeStatus->isOpen() ? 'No abandoned carts.' : "Nothing {$activeStatus->value}." }}
                </p>
                <p class="text-xs text-ink-3">
                    @if ($activeStatus->isOpen())
                        Carts show up here when a buyer fills in their email at checkout and then leaves.
                    @else
                        Carts move here once they are recovered or expire.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if ($carts->hasPages())
        <div class="mt-4">{{ $carts->links() }}</div>
    @endif
@endsection