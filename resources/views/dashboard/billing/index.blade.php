@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Billing"
        subtitle="What you pay, when it renews, and how to change it."
    />

    <div class="max-w-4xl mx-auto space-y-4">
        @unless ($subscriptionsEnabled)
            <div class="rounded-2xl border border-warn-edge bg-warn-wash p-5 text-sm text-warn-text">
                Seller subscriptions are temporarily disabled by mypage.ng. Your storefront and dashboard remain available, and your existing billing record has been kept.
            </div>
        @endunless
        @if ($subscription)
            @php
                $merchantStatus = $subscription->merchantStatus();
                $statusLabel = $subscription->trial_ends_at?->isFuture() ? 'Free trial' : $merchantStatus->label();
                $graceEndsAt = $subscription->graceEndsAt();
                $daysLeft = $subscription->daysLeft();
            @endphp
            <div class="rounded-2xl border border-rule bg-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-ink-3">Your plan</p>
                        <h2 class="mt-1 text-xl font-display font-bold text-ink">
                            {{ $subscription->plan?->name ?? 'Plan removed' }}
                        </h2>
                        <p class="mt-1 text-xs text-ink-3">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $merchantStatus->badgeClasses() }}">
                                {{ $statusLabel }}
                            </span>
                            @if ($daysLeft !== null)
                                <span class="ml-1 text-[10px] text-ink-3">{{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left</span>
                            @endif
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-xl font-extrabold text-ink">{{ \App\Shared\Support\Money::format($subscription->price_kobo) }}</p>
                        <p class="text-[11px] text-ink-3">per {{ rtrim($subscription->plan?->interval ?? 'month', 'ly') }}</p>
                    </div>
                </div>

                <dl class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-rule pt-5 text-xs">
                    @if ($subscription->trial_ends_at)
                        <div>
                            <dt class="text-ink-3">Trial ends</dt>
                            <dd class="mt-0.5 font-semibold text-ink">{{ $subscription->trial_ends_at->format('j M Y') }}</dd>
                        </div>
                    @endif

                    @if ($subscription->current_period_ends_at)
                        <div>
                            <dt class="text-ink-3">{{ $subscription->cancelled_at ? 'Ends' : 'Renews' }}</dt>
                            <dd class="mt-0.5 font-semibold text-ink">
                                {{ $subscription->current_period_ends_at->format('j M Y') }}
                                @if ($subscription->daysUntilRenewal() !== null)
                                    <span class="text-ink-3">
                                        ({{ abs($subscription->daysUntilRenewal()) }} days)
                                    </span>
                                @endif
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-ink-3">Payment</dt>
                        <dd class="mt-0.5 font-semibold capitalize text-ink">{{ $subscription->payment_method }}</dd>
                    </div>

                    {{-- The gateway's own date, not ours: it is what the card will
                         actually be debited on, and it is the one that moves. --}}
                    @if ($subscription->next_payment_date)
                        <div>
                            <dt class="text-ink-3">Card charged</dt>
                            <dd class="mt-0.5 font-semibold text-ink">{{ $subscription->next_payment_date->format('j M Y') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($merchantStatus === \App\Domains\Billing\Enums\MerchantStatus::Grace)
                    <div class="mt-5 rounded-xl border border-warn-edge bg-warn-wash p-4">
                        <p class="text-xs font-semibold text-warn-text">
                            Your last payment did not go through.
                        </p>
                        <p class="mt-1 text-[11px] text-warn-text">
                            Your page stays online and your customers keep buying throughout the grace period.
                            You have {{ $graceEndsAt?->diffForHumans() ?? $graceDays.' days' }} left to sort it out.
                        </p>
                    </div>
                @elseif ($merchantStatus->isLapsed())
                    <div class="mt-5 rounded-xl border border-danger-edge bg-danger-wash p-4">
                        <p class="text-xs font-semibold text-danger-text">
                            {{ $merchantStatus->label() }}: your dashboard is read-only and your storefront is not accepting new orders.
                        </p>
                        <p class="mt-1 text-[11px] text-danger-text">
                            {{ $merchantStatus->description() }} Renew below to reopen your shop.
                        </p>
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    {{-- A recurring subscription can only be saved by fixing the
                         card, and the card must not travel through this app, so
                         the gateway hosts the page. --}}
                    @if ($subscription->gateway_subscription_code)
                        <a href="{{ route('billing.manage-card') }}"
                           class="rounded-xl bg-brand px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                            Update card
                        </a>
                    @endif

                    @unless ($subscription->cancelled_at)
                        <form method="POST" action="{{ route('billing.cancel') }}"
                              onsubmit="return confirm('Cancel your subscription? You keep everything until {{ $subscription->current_period_ends_at?->format('j M Y') ?? 'this period' }} ends.')">
                            @csrf
                            <button class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface">
                                Cancel subscription
                            </button>
                        </form>
                    @else
                        <span class="text-[11px] text-ink-3">
                            Cancelled. Your plan runs until {{ $subscription->current_period_ends_at?->format('j M Y') ?? 'the period ends' }}.
                        </span>
                    @endunless
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-rule bg-card p-6">
                @if ($subscriptionsEnabled)
                    <p class="text-sm font-semibold text-ink">You are not subscribed yet.</p>
                    <p class="mt-1 text-xs text-ink-3">Pick a plan below to keep your page and your dashboard open.</p>
                @else
                    <p class="text-sm font-semibold text-ink">No active subscription.</p>
                    <p class="mt-1 text-xs text-ink-3">Subscription signup is currently paused by the platform. Your storefront remains available.</p>
                @endif
            </div>
        @endif

        @if ($subscriptionsEnabled)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($plans as $plan)
                <div @class([
                    'rounded-2xl border bg-card p-5 flex flex-col',
                    'border-brand shadow-subtle' => $plan->is_featured,
                    'border-rule' => ! $plan->is_featured,
                ])>
                    @if ($plan->is_featured)
                        <span class="mb-2 self-start rounded-full bg-brand-wash px-2 py-0.5 text-[10px] font-semibold text-brand-text">
                            Most popular
                        </span>
                    @endif

                    <h3 class="text-sm font-bold text-ink">{{ $plan->name }}</h3>
                    <p class="mt-1 text-xl font-extrabold text-ink">{{ $plan->priceLabel() }}</p>
                    <p class="mt-2 flex-1 text-xs text-ink-3">{{ $plan->description }}</p>

                    <ul class="mt-3 space-y-1 text-[11px] text-ink-3">
                        <li>{{ $plan->max_products === null ? 'Unlimited' : $plan->max_products }} products</li>
                        @if ($plan->trial_days)
                            <li>{{ $plan->trial_days }}-day free trial</li>
                        @endif
                    </ul>

                    <form method="POST" action="{{ route('billing.subscribe') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="plan" value="{{ $plan->slug }}">
                        <button @class([
                            'w-full rounded-xl px-4 py-2.5 text-xs font-semibold',
                            'bg-brand text-white hover:bg-brand-hover' => $plan->is_featured,
                            'border border-rule text-ink hover:bg-surface' => ! $plan->is_featured,
                        ])>
                            Choose {{ $plan->name }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
        @endif

        @if ($history->isNotEmpty())
            <div class="rounded-2xl border border-rule bg-card overflow-hidden">
                <h2 class="border-b border-rule px-5 py-4 text-[11px] uppercase tracking-wide text-ink-3">
                    Payments
                </h2>
                @foreach ($history as $payment)
                    <div class="flex items-center justify-between gap-4 border-b border-rule px-5 py-3 last:border-0">
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ $payment->reference }}</p>
                            <p class="text-[11px] text-ink-3">{{ $payment->created_at->format('j M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-ink">{{ \App\Shared\Support\Money::format($payment->amount_kobo) }}</p>
                            <p class="text-[11px] capitalize text-ink-3">{{ $payment->status->value }}</p>
                        </div>
                    </div>
                @endforeach

                @if ($history->hasPages())
                    <div class="border-t border-rule px-5 py-4">{{ $history->links() }}</div>
                @endif
            </div>
        @endif

        @if ($subscriptionsEnabled)
        <div class="rounded-2xl border border-rule bg-card p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">Pay by bank transfer</h2>
            <p class="text-xs text-ink-3 mb-4">{{ $transfers['instructions'] }}</p>

            @if (! empty($transfers['account_number']))
                <dl class="mb-4 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <dt class="text-ink-3">Bank</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $transfers['bank_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-3">Account number</dt>
                        <dd class="mt-0.5 font-semibold text-ink">{{ $transfers['account_number'] }}</dd>
                    </div>
                </dl>
            @endif

            <form method="POST" action="{{ route('billing.manual') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label class="block">
                    <span class="text-xs font-semibold text-ink">Plan</span>
                    <select name="plan" class="mt-1 rounded-xl border border-rule px-3 py-2 text-sm">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->slug }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Payment reference</span>
                    <input type="text" name="reference" required
                           class="mt-1 rounded-xl border border-rule px-3 py-2 text-sm"
                           placeholder="The reference on your transfer">
                </label>

                <button class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink hover:bg-surface">
                    I have transferred
                </button>
            </form>
        </div>
        @endif
    </div>
@endsection
