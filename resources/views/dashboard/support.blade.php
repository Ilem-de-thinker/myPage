@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Support"
        subtitle="Ask mypage.ng about your account, billing, storefront, or a technical problem."
        width="max-w-3xl mx-auto"
    />

    <div class="mx-auto grid max-w-3xl gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="POST" action="{{ route('support.store') }}" class="rounded-2xl border border-rule bg-card p-6">
            @csrf
            <h2 class="text-sm font-bold text-ink">Start a support request</h2>
            <div class="mt-5 space-y-4">
                <label class="block text-xs font-semibold text-ink-2">What do you need help with?
                    <select name="category" required class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm text-ink">
                        @foreach (['account' => 'Account', 'billing' => 'Billing or payments', 'technical' => 'Technical problem', 'storefront' => 'Storefront', 'other' => 'Something else'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-semibold text-ink-2">Subject
                    <input name="subject" value="{{ old('subject') }}" maxlength="180" required class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm text-ink" placeholder="What is happening?">
                </label>
                <label class="block text-xs font-semibold text-ink-2">Details
                    <textarea name="body" rows="7" minlength="20" maxlength="4000" required class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm text-ink" placeholder="Include the page, order reference, or exact error if relevant.">{{ old('body') }}</textarea>
                </label>
                <button class="rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-brand-ink">Send to support</button>
            </div>
        </form>

        <aside class="h-fit rounded-2xl border border-rule bg-surface p-5 text-xs text-ink-2">
            <h2 class="font-bold text-ink">What happens next?</h2>
            <p class="mt-2 leading-relaxed">Your request goes to the mypage.ng support queue. Keep the subject specific so the right person can pick it up quickly.</p>
            <p class="mt-3 leading-relaxed">For a buyer issue on one store, include the store name and order or booking reference.</p>
        </aside>
    </div>

    @if ($requests->isNotEmpty())
        <section class="mx-auto mt-8 max-w-3xl">
            <h2 class="text-sm font-bold text-ink">Your tickets</h2>
            <div class="mt-3 space-y-3">
                @foreach ($requests as $supportRequest)
                    <a href="{{ route('support.show', $supportRequest) }}" class="block rounded-2xl border border-rule bg-card p-5 transition hover:border-brand-edge hover:bg-tint">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-ink">{{ $supportRequest->subject }}</h3>
                                <p class="mt-1 text-[11px] font-bold tracking-wide text-brand">{{ $supportRequest->ticket_number }}</p>
                            </div>
                            <span class="rounded-full bg-tint px-2.5 py-1 text-[11px] font-semibold text-ink-2">{{ $supportRequest->status->label() }}</span>
                        </div>
                        <p class="mt-2 text-[11px] text-ink-3">{{ ucfirst($supportRequest->source) }} · {{ str_replace('_', ' ', $supportRequest->category) }} · {{ $supportRequest->created_at?->format('j M Y') }}</p>
                        <span class="mt-4 inline-flex text-xs font-bold text-brand">Open ticket →</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
