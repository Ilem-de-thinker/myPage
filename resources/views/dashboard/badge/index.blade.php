@extends('layouts.app')

@section('content')
    @php
    use App\Domains\Admin\Models\BadgeStatus;

    $status = $application?->status;
@endphp

    <x-ui.page-header
        title="Verify your business"
        subtitle="One document, looked at by a person. Verification is a trust signal; it does not route or hold buyer payments."
        width="max-w-2xl mx-auto"
    />

    <div class="max-w-2xl mx-auto space-y-4">
        @if ($status !== null && $status !== BadgeStatus::Approved)
            <div class="rounded-2xl border border-rule bg-card px-6 py-5">
                <p class="text-xs font-semibold text-ink">While you wait, this is what is locked</p>
                <ul class="mt-2 space-y-1 text-[11px] text-ink-3">
                    <li>A verified tick on your storefront and listings</li>
                    <li>Higher trust when buyers compare businesses</li>
                    <li>Clearer identity information for moderation</li>
                </ul>
                <p class="mt-2 text-[11px] text-ink-3">You can keep building your page and accepting direct gateway payments while the review is pending.</p>
            </div>
        @endif

        @if ($status === BadgeStatus::Approved)
            <div class="rounded-2xl border border-ok-edge bg-ok-wash p-6 text-center">
                <p class="text-sm font-semibold text-ok-text">Your page is verified.</p>
                <p class="mt-1 text-xs text-ok-text">
                    The tick now shows on {{ $page->name }}'s public page.
                </p>
            </div>
        @elseif ($status === BadgeStatus::Rejected)
            <div class="rounded-2xl border border-danger-edge bg-danger-wash p-6">
                <p class="text-sm font-semibold text-danger-text">We could not verify that one.</p>
                <p class="mt-1 text-xs text-danger-text">{{ $application->rejection_reason }}</p>
                <p class="mt-2 text-xs text-danger-text">
                    Fix what was asked for and send it again below.
                </p>
            </div>
        @elseif ($status !== null)
            <div class="rounded-2xl border border-warn-edge bg-warn-wash p-6">
                <p class="text-sm font-semibold text-warn-text">
                    Waiting on us — sent {{ $application->submitted_at->diffForHumans() }}.
                </p>
                <p class="mt-1 text-xs text-warn-text">
                    Applications are looked at every weekday. You can send a better document below if the
                    one you sent is wrong.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('badge.submit') }}" enctype="multipart/form-data" class="rounded-2xl border border-rule bg-card p-6 space-y-4">
            @csrf

            <label class="block">
                <span class="text-xs font-semibold text-ink">Storefront business name</span>
                <input type="text" name="business_name" required value="{{ old('business_name', $application?->business_name ?? $page->name) }}"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                <span class="mt-1 block text-[11px] text-ink-3">The public name buyers see.</span>
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">Registered legal name</span>
                <input type="text" name="registered_legal_name" required value="{{ old('registered_legal_name', $application?->registered_legal_name ?? $page->name) }}"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                <span class="mt-1 block text-[11px] text-ink-3">The name printed on your CAC record or identity document.</span>
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">CAC/RC or identity number</span>
                <input type="text" name="registration_number" required value="{{ old('registration_number', $application?->registration_number) }}"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">Document expiry date <span class="font-normal text-ink-3">(required for national ID)</span></span>
                <input type="date" name="document_expires_at" value="{{ old('document_expires_at', $application?->document_expires_at?->format('Y-m-d')) }}"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">What are you sending?</span>
                <select name="document_type" class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    <option value="cac" @selected(old('document_type', $application?->document_type) === 'cac')>CAC certificate</option>
                    <option value="national_id" @selected(old('document_type', $application?->document_type) === 'national_id')>National ID (passport, driver's licence, NIN)</option>
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">Upload it</span>
                <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png"
                       class="mt-1 w-full text-xs text-ink-2 file:mr-3 file:rounded-lg file:border-0 file:bg-tint file:px-3 file:py-2 file:text-xs file:font-semibold">
                <span class="mt-1 block text-[11px] text-ink-3">PDF or image, up to 8 MB. It is stored privately for {{ config('legal.kyc_retention_years') }} years after submission.</span>
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">Anything we should know?</span>
                <textarea name="note" rows="2" class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                          placeholder="Trading name differs from the registered name.">{{ old('note') }}</textarea>
            </label>

            <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                Send for review
            </button>
        </form>
    </div>
@endsection
