@extends('layouts.app')

@section('content')
    <x-ui.page-header
        :title="$customer->displayName()"
        subtitle="Customer since {{ $customer->created_at->format('M Y') }}"
        :back="route('customers.index')"
        back-label="Back to customers"
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-card rounded-2xl border border-rule p-5">
                <p class="text-[11px] uppercase tracking-wide text-ink-3">Lifetime value</p>
                <p class="mt-1 text-xl font-extrabold text-ink">{{ \App\Shared\Support\Money::format($customer->lifetime_value_kobo) }}</p>
            </div>
            <div class="bg-card rounded-2xl border border-rule p-5">
                <p class="text-[11px] uppercase tracking-wide text-ink-3">{{ \Illuminate\Support\Str::plural($customer->purchaseNoun(), $customer->purchaseCount()) }}</p>
                <p class="mt-1 text-xl font-extrabold text-ink">{{ $customer->purchaseCount() }}</p>
            </div>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-4">Details</h2>

            <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Name</span>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $customer->name) }}"
                            class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Phone</span>
                        <input
                            type="text"
                            name="phone"
                            value="{{ old('phone', $customer->phone) }}"
                            placeholder="0803 000 0000"
                            class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                        >
                    </label>
                </div>

                <label class="flex items-start gap-2.5 rounded-xl bg-surface p-3">
                    <input type="checkbox" name="opted_in" value="1" @checked($customer->opted_in) class="mt-0.5 rounded border-rule-strong">
                    <span>
                        <span class="block text-xs font-semibold text-ink">Opted in to messages</span>
                        <span class="block text-[11px] text-ink-3">
                            Only tick this if the buyer has asked you to send them updates and offers. You can never message
                            an unchecked customer.
                        </span>
                    </span>
                </label>

                <button class="rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-hover">Save</button>
            </form>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">{{ \Illuminate\Support\Str::plural($customer->purchaseNoun(), $customer->purchaseCount()) }}</h2>

            @forelse ($customer->orders as $order)
                <div class="flex flex-wrap items-center gap-3 border-b border-rule py-3 last:border-0">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('dashboard.orders.show', $order) }}" class="text-sm font-semibold text-ink hover:text-brand">
                            {{ $order->reference }}
                        </a>
                        <span class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $order->status->badgeClasses() }}">
                            {{ $order->status->label() }}
                        </span>
                        <p class="mt-0.5 truncate text-xs text-ink-3">
                            {{ $order->items->pluck('name')->join(', ') ?: 'No items' }}
                        </p>
                    </div>
                    <p class="text-sm font-bold text-ink">{{ \App\Shared\Support\Money::format($order->total_kobo) }}</p>
                    <p class="w-24 shrink-0 text-right text-[11px] text-ink-3">{{ $order->created_at->format('M d, Y') }}</p>
                </div>
            @empty
                <p class="py-4 text-xs text-ink-3">No orders on record.</p>
            @endforelse
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-3">Notes</h2>

            <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mb-4">
                @csrf
                <textarea
                    name="body"
                    rows="3"
                    required
                    placeholder="Called about a delivery address, prefers WhatsApp..."
                    class="w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                >{{ old('body') }}</textarea>
                @error('body')
                    <p class="mt-1 text-xs text-danger-text">{{ $message }}</p>
                @enderror
                <button class="mt-2 rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-hover">Add note</button>
            </form>

            <ul class="space-y-2">
                @forelse ($customer->notes as $note)
                    <li class="flex items-start justify-between gap-4 rounded-xl bg-surface p-3">
                        <div>
                            <p class="text-sm text-ink whitespace-pre-line">{{ $note->body }}</p>
                            <p class="mt-1 text-[11px] text-ink-3">{{ $note->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        <form method="POST" action="{{ route('customers.notes.destroy', [$customer, $note]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-[11px] font-semibold text-ink-3 hover:text-danger-text">Delete</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2 text-xs text-ink-3">No notes yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection