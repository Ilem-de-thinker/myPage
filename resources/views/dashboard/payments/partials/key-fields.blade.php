@php
    $isPaystack = $key === 'paystack';
    $secretExample = $environments['expected'] === 'live'
        ? ($isPaystack ? 'sk_live_…' : 'FLWSECK-…')
        : ($isPaystack ? 'sk_test_…' : 'FLWSECK_TEST-…');
@endphp

<label class="block">
    <span class="text-xs font-semibold text-ink">Secret key</span>
    <input type="password" name="secret_key" required autocomplete="new-password"
           placeholder="{{ $record ? 'Paste the new secret key' : $secretExample }}"
           class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 font-mono text-sm focus:border-brand focus:outline-none">
    <span class="mt-1 block text-[11px] text-ink-3">
        Starts with {{ $secretExample }}. It is encrypted before storage and never shown again.
    </span>
    @if ($isCurrentForm)
        @error('secret_key') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
    @endif
</label>

<label class="block">
    <span class="text-xs font-semibold text-ink">Public key <span class="font-normal text-ink-3">(optional)</span></span>
    <input type="text" name="public_key" value="{{ $isCurrentForm ? old('public_key', $record?->public_key) : $record?->public_key }}"
           autocomplete="off" placeholder="{{ $isPaystack ? 'pk_test_…' : 'FLWPUBK_TEST-…' }}"
           class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 font-mono text-sm focus:border-brand focus:outline-none">
    <span class="mt-1 block text-[11px] text-ink-3">
        Used for inline checkout. Safe to show on your storefront.
    </span>
    @if ($isCurrentForm)
        @error('public_key') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
    @endif
</label>

<label class="block">
    <span class="text-xs font-semibold text-ink">Webhook secret <span class="font-normal text-ink-3">(optional)</span></span>
    <input type="password" name="webhook_secret" autocomplete="new-password"
           placeholder="{{ $record ? 'Leave blank to keep the one you saved' : 'Only needed if your gateway uses a separate signing secret' }}"
           class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 font-mono text-sm focus:border-brand focus:outline-none">
    @if ($isCurrentForm)
        @error('webhook_secret') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
    @endif
</label>
