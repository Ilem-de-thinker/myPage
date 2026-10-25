@props(['page' => null, 'seller' => false, 'aiFeature' => null, 'standalone' => true])

@php
    $isStorefront = $page !== null;
    $storeSupportAction = $isStorefront
        ? \App\Shared\Support\StoreUrl::path('storefront.support', ['slug' => $page->slug])
        : route('support.store');
    $reportAction = $isStorefront
        ? \App\Shared\Support\StoreUrl::path('storefront.report', ['slug' => $page->slug])
        : null;
@endphp

<div
    x-data="platformSupport"
    x-on:support-open.window="openSupport()"
    @ai.open.window="closeSupport()"
    class="relative z-50"
>
    @if ($standalone)
        <div class="platform-support-launcher fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] right-4 z-40 flex flex-col items-end gap-3 md:bottom-6 md:right-6">
            @if ($aiFeature)
                <x-ai.launcher :feature="$aiFeature" />
            @endif
            <button type="button" @click="openSupport()" class="inline-flex min-h-16 items-center gap-2.5 rounded-full bg-brand px-6 py-4 text-base font-bold text-brand-ink shadow-xl shadow-ink/10 transition hover:-translate-y-0.5 hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2" aria-label="Open support form">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.7 9.7 0 0 1-4.2-1L3 21l1.5-4.1A8.4 8.4 0 0 1 3 11.5 8.5 8.5 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5Z" /><path d="M8 12h.01M12 12h.01M16 12h.01" stroke-linecap="round" /></svg>
            <span>Support</span>
            </button>
        </div>
    @endif

    <div x-cloak x-show="supportOpen" x-transition.opacity @keydown.escape.window="closeSupport()" style="display: none" class="fixed inset-0 flex items-end justify-end bg-black/30 p-4 md:p-6" role="dialog" aria-modal="true" aria-label="Support form">
        <div x-show="supportOpen" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" @click.outside="closeSupport()" class="max-h-[calc(100dvh-7rem)] w-full max-w-md overflow-y-auto rounded-2xl border border-rule bg-card p-5 text-ink shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-brand">{{ $isStorefront ? 'Help with this page' : 'mypage.ng support' }}</p>
                    <h2 class="mt-1 text-lg font-bold">How can we help?</h2>
                    <p class="mt-1 text-xs leading-relaxed text-ink-3">{{ $isStorefront ? 'Choose whether your message is for the store or for mypage.ng.' : 'Send a request about your account, storefront, billing, or a technical problem.' }}</p>
                </div>
                <button type="button" @click="closeSupport()" class="rounded-lg p-1.5 text-ink-3 hover:bg-tint hover:text-ink" aria-label="Close support form"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
            </div>

            @if ($isStorefront)
                <div class="mt-5 grid grid-cols-2 gap-1 rounded-xl bg-tint p-1" role="tablist" aria-label="Support destination">
                    <button type="button" role="tab" :aria-selected="supportTab === 'store'" @click="supportTab = 'store'" :class="supportTab === 'store' ? 'bg-card text-ink shadow-sm' : 'text-ink-3'" class="rounded-lg px-3 py-2 text-xs font-bold">Contact store</button>
                    <button type="button" role="tab" :aria-selected="supportTab === 'platform'" @click="supportTab = 'platform'" :class="supportTab === 'platform' ? 'bg-card text-ink shadow-sm' : 'text-ink-3'" class="rounded-lg px-3 py-2 text-xs font-bold">Report to mypage.ng</button>
                </div>

                <form x-show="supportTab === 'store'" method="POST" action="{{ $storeSupportAction }}" class="mt-5 grid gap-3">
                    @csrf
                    <input type="hidden" name="destination" value="store">
                    <label class="text-xs font-semibold text-ink-2">Topic<select name="category" required class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink"><option value="question">General question</option><option value="payment">Payment</option><option value="order">Order or booking</option><option value="technical">Technical problem</option><option value="other">Other</option></select></label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-ink-2">Your name (optional)<input name="requester_name" maxlength="120" class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink placeholder:text-ink-3"></label>
                        <label class="text-xs font-semibold text-ink-2">Your contact (optional)<input name="requester_contact" maxlength="190" class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink placeholder:text-ink-3"></label>
                    </div>
                    <label class="text-xs font-semibold text-ink-2">Subject<input name="subject" maxlength="180" required class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink placeholder:text-ink-3" placeholder="What do you need from the store?"></label>
                    <label class="text-xs font-semibold text-ink-2">Message<textarea name="body" required minlength="20" maxlength="4000" rows="4" class="mt-1 w-full rounded-xl border border-rule-strong bg-card p-3 text-xs text-ink placeholder:text-ink-3" placeholder="Ask the store a question or explain an order issue."></textarea></label>
                    <button class="inline-flex w-full items-center justify-center rounded-xl bg-brand px-4 py-3 text-xs font-bold text-brand-ink">Send to store</button>
                </form>

                <form x-cloak x-show="supportTab === 'platform'" method="POST" action="{{ $reportAction }}" class="mt-5 grid gap-3">
                    @csrf
                    <label class="text-xs font-semibold text-ink-2">What happened?<select name="category" required class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink"><option value="fraud">Fraud or scam</option><option value="not_delivered">Goods or service not delivered</option><option value="not_as_described">Not as described</option><option value="misleading_listing">Misleading listing</option><option value="payment_problem">Payment problem</option><option value="other">Other</option></select></label>
                    <label class="text-xs font-semibold text-ink-2">Your contact (optional)<input name="reporter_contact" maxlength="190" class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink placeholder:text-ink-3"></label>
                    <label class="text-xs font-semibold text-ink-2">Details<textarea name="body" required minlength="20" maxlength="2000" rows="5" class="mt-1 w-full rounded-xl border border-rule-strong bg-card p-3 text-xs text-ink placeholder:text-ink-3" placeholder="Tell mypage.ng what happened and include an order reference if relevant."></textarea></label>
                    <button class="inline-flex w-full items-center justify-center rounded-xl bg-ink px-4 py-3 text-xs font-bold text-card">Send report to mypage.ng</button>
                </form>
            @else
                <form method="POST" action="{{ $storeSupportAction }}" class="mt-5 grid gap-3">
                    @csrf
                    <label class="text-xs font-semibold text-ink-2">Topic<select name="category" required class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink"><option value="account">Account</option><option value="billing">Billing or payments</option><option value="technical">Technical problem</option><option value="storefront">Storefront</option><option value="other">Something else</option></select></label>
                    <label class="text-xs font-semibold text-ink-2">Subject<input name="subject" maxlength="180" required class="mt-1 h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-xs text-ink placeholder:text-ink-3" placeholder="What do you need help with?"></label>
                    <label class="text-xs font-semibold text-ink-2">Details<textarea name="body" required minlength="20" maxlength="4000" rows="4" class="mt-1 w-full rounded-xl border border-rule-strong bg-card p-3 text-xs text-ink placeholder:text-ink-3" placeholder="Tell us what happened."></textarea></label>
                    <button class="inline-flex w-full items-center justify-center rounded-xl bg-brand px-4 py-3 text-xs font-bold text-brand-ink">Send to support</button>
                </form>
            @endif
        </div>
    </div>
</div>
