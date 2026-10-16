@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Customers"
        subtitle="Everyone who has ordered from you, built automatically."
    />

    <form method="GET" action="{{ route('customers.index') }}" class="mb-4 max-w-sm">
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search by name, phone or email"
            class="w-full rounded-xl border border-rule bg-card px-4 py-2.5 text-sm text-ink placeholder:text-ink-3 focus:border-brand focus:outline-none"
        >
    </form>

    <div class="bg-card rounded-2xl border border-rule overflow-hidden">
        @forelse ($customers as $customer)
            <div class="flex flex-wrap items-center gap-3 border-b border-rule px-4 py-3 last:border-0 hover:bg-surface/60">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('customers.show', $customer) }}" class="text-sm font-semibold text-ink hover:text-brand">
                            {{ $customer->displayName() }}
                        </a>
                        @if ($customer->opted_in)
                            <span class="rounded-full bg-ok-wash px-2 py-0.5 text-[10px] font-semibold text-ok-text">Opted in</span>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-xs text-ink-3">
                        {{ collect([$customer->phone, $customer->email])->filter()->join(' &middot; ') ?: 'No contact details' }}
                    </p>
                </div>

                <div class="text-right">
                    <p class="text-sm font-bold text-ink">{{ \App\Shared\Support\Money::format($customer->lifetime_value_kobo) }}</p>
                    <p class="text-[11px] text-ink-3">
                        {{ $customer->purchaseCount() }} {{ \Illuminate\Support\Str::plural($customer->purchaseNoun(), $customer->purchaseCount()) }}
                        @if ($customer->last_activity_at)
                            &middot; {{ $customer->last_activity_at->diffForHumans() }}
                        @endif
                    </p>
                </div>

                <a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-brand">View</a>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm text-ink-3 mb-1">
                    {{ $search ? 'No customers match that search.' : 'No customers yet.' }}
                </p>
                <p class="text-xs text-ink-3">
                    @if ($search)
                        Try a different name, phone number or email.
                    @else
                        Anyone who orders from your page, over WhatsApp or at checkout, shows up here on their own.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if ($customers->hasPages())
        <div class="mt-4">{{ $customers->links() }}</div>
    @endif
@endsection