@extends('layouts.app')

@section('content')
    @php
        use App\Shared\Support\Money;
        use App\Domains\Payments\Enums\PayoutStatus;

        $canWithdraw = $balance >= $minimum;
    @endphp

    <x-ui.page-header
        title="Withdraw"
        subtitle="Your money, your bank account, no waiting for anyone to approve it."
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        <div class="rounded-2xl border border-rule bg-card p-6">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="text-[11px] uppercase tracking-wide text-ink-3">Available to withdraw</p>
                    <p class="mt-1 font-display text-3xl font-extrabold tabular-naira">{{ Money::format($balance) }}</p>
                </div>

                @if ($inFlight > 0)
                    <div class="text-right">
                        <p class="text-[11px] uppercase tracking-wide text-ink-3">On its way</p>
                        <p class="mt-1 text-sm font-bold text-ink-2">{{ Money::format($inFlight) }}</p>
                    </div>
                @endif
            </div>

            @if ($heldInEscrow > 0)
                <p class="mt-4 rounded-xl bg-surface px-4 py-3 text-xs text-ink-2">
                    <span class="font-semibold">{{ Money::format($heldInEscrow) }}</span> of your sales is still
                    held in escrow. That money becomes withdrawable once the buyer is satisfied or the
                    hold period passes &mdash; it is already yours, just not yet released.
                </p>
            @endif

            @if ($succeeded > 0)
                <p class="mt-3 text-[11px] text-ink-3">
                    {{ $succeeded }} payout{{ $succeeded === 1 ? '' : 's' }} completed so far.
                </p>
            @endif
        </div>

        {{-- Withdraw --}}
        <div class="rounded-2xl border border-rule bg-card p-6">
            <h2 class="text-sm font-bold text-ink">Withdraw to your bank</h2>

            @if (! $canWithdraw)
                <div class="mt-4 rounded-xl bg-surface px-4 py-4 text-xs text-ink-2">
                    You need at least {{ Money::format($minimum) }} available to withdraw.
                    @if ($heldInEscrow > 0)
                        Some of your money is still in escrow.
                    @endif
                </div>
            @elseif (! auth()->user()->payout_account_number)
                <p class="mt-2 text-xs text-ink-3">
                    Save your bank details below first. We check the account name against the bank
                    before a single naira moves.
                </p>
            @else
                <form method="POST" action="{{ route('payouts.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Amount</span>
                        <div class="mt-1 flex gap-2">
                            <input type="number" name="amount_kobo" required
                                   min="{{ $minimum }}" max="{{ $balance }}" step="1"
                                   value="{{ $balance }}"
                                   class="w-full rounded-xl border border-rule-strong px-4 py-2.5 text-sm focus:border-brand focus:outline-none">
                            <button type="button" onclick="const i=this.closest('label').querySelector('input');i.value={{ $balance }};i.dispatchEvent(new Event('input'))"
                                    class="shrink-0 rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface">
                                Max
                            </button>
                        </div>
                        <span class="mt-1 block text-[11px] text-ink-3">
                            Entered in kobo, so {{ $minimum }} is the smallest allowed withdrawal
                            ({{ Money::format($minimum) }}). Available: {{ Money::format($balance) }}.
                        </span>
                        @error('amount_kobo')
                            <span class="mt-1 block text-xs font-semibold text-danger-text">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="rounded-xl bg-surface px-4 py-3 text-xs text-ink-2">
                        Paying <span class="font-semibold">{{ auth()->user()->payout_account_name }}</span>
                        &middot; {{ auth()->user()->payout_bank_code }}
                        {{ auth()->user()->payout_account_number }}
                    </div>

                    <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                        Withdraw {{ Money::format($balance) }}
                    </button>
                </form>
            @endif
        </div>

        {{-- Bank details --}}
        <div class="rounded-2xl border border-rule bg-card p-6">
            <h2 class="text-sm font-bold text-ink">Bank details</h2>
            <p class="mt-1 text-xs text-ink-3">
                We confirm the account name with the bank before saving. A mismatch is the only
                warning you get before money goes to the wrong person.
            </p>

            <form method="POST" action="{{ route('payouts.bank') }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Bank</span>
                        <select name="payout_bank_code" required class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm">
                            @foreach (\App\Shared\Support\NigerianBanks::all() as $code => $name)
                                <option value="{{ $code }}" @selected(auth()->user()->payout_bank_code === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Account number</span>
                        <input type="text" name="payout_account_number" required inputmode="numeric"
                               value="{{ old('payout_account_number', auth()->user()->payout_account_number) }}"
                               class="mt-1 w-full rounded-xl border border-rule-strong px-4 py-2.5 text-sm focus:border-brand focus:outline-none">
                        @error('payout_account_number')
                            <span class="mt-1 block text-xs font-semibold text-danger-text">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <button class="rounded-xl border border-rule px-5 py-2.5 text-xs font-semibold text-ink hover:bg-surface">
                    Save bank details
                </button>
            </form>
        </div>

        {{-- History --}}
        @if ($payouts->isNotEmpty())
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="mb-3 text-[11px] uppercase tracking-wide text-ink-3">History</h2>
                <ul class="divide-y divide-rule">
                    @foreach ($payouts as $payout)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-xs">
                            <div>
                                <p class="font-semibold text-ink">{{ Money::format($payout->amount_kobo) }}</p>
                                <p class="mt-0.5 text-ink-3">
                                    {{ $payout->reference }} &middot; {{ $payout->account_name }}
                                    ({{ $payout->bank_code }} {{ $payout->account_number }})
                                </p>
                                @if ($payout->failure_reason)
                                    <p class="mt-0.5 text-danger-text">{{ $payout->failure_reason }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold {{ match ($payout->status) {
                                    PayoutStatus::Paid => 'bg-ok-wash text-ok-text',
                                    PayoutStatus::Pending => 'bg-warn-wash text-warn-text',
                                    default => 'bg-danger-wash text-danger-text',
                                } }}">
                                    {{ $payout->status->value }}
                                </span>
                                <p class="mt-1 text-ink-3">{{ $payout->created_at->format('j M Y') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($payouts->hasPages())
                    <div class="mt-4">{{ $payouts->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
