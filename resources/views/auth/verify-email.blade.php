@extends('layouts.guest')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight mb-1">Check your email</h1>
    <p class="text-ink-3 text-sm mb-6">Enter the 6-digit code we sent to <strong>{{ auth()->user()->email }}</strong>.</p>

    <form method="POST" action="{{ route('verification.verify') }}" id="otp-form">
        @csrf
        <input type="hidden" name="code" id="otp-code">

        <div class="flex gap-2 justify-center mb-2" id="otp-boxes">
            @for ($i = 1; $i <= 6; $i++)
                <input type="text" inputmode="numeric" maxlength="1" data-idx="{{ $i }}"
                       class="otp-digit w-12 h-14 text-center text-xl font-bold rounded-lg border border-rule-strong focus:border-brand focus:ring-brand"
                       aria-label="Digit {{ $i }}" autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}">
            @endfor
        </div>

        @error('code') <p class="text-danger-text text-xs text-center mb-2">{{ $message }}</p> @enderror

        <button class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 hover:bg-brand-hover mt-4">Verify email</button>
    </form>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
        @csrf
        <button class="w-full rounded-lg border border-rule-strong text-ink-2 font-medium py-2.5 hover:bg-tint">Resend code</button>
    </form>

    <script>
        const digits = [...document.querySelectorAll('.otp-digit')];
        digits.forEach((el, i) => {
            el.addEventListener('input', e => {
                el.value = el.value.replace(/\D/g, '').slice(-1);
                if (el.value && i < 5) digits[i + 1].focus();
                syncCode();
            });
            el.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !el.value && i > 0) digits[i - 1].focus();
            });
            el.addEventListener('paste', e => {
                e.preventDefault();
                const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                text.split('').forEach((c, j) => digits[j].value = c);
                digits[Math.min(text.length, 5)].focus();
                syncCode();
            });
        });
        function syncCode() {
            document.getElementById('otp-code').value = digits.map(d => d.value).join('');
        }
        document.getElementById('otp-form').addEventListener('submit', syncCode);
    </script>
@endsection
