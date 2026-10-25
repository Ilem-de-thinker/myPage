@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Payment keys"
        subtitle="Your customers pay you directly. mypage.ng never holds their money."
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        <div class="rounded-2xl border border-rule bg-surface p-5">
            <h2 class="text-xs font-bold uppercase tracking-wide text-ink-3">Which key to use here</h2>
            <p class="mt-2 text-xs leading-relaxed text-ink-2">
                @if ($environments['expected'] === 'live')
                    This site is live, so only a <strong>live</strong> key can take real payments. Test keys are refused.
                @else
                    This is not a live site, so use a <strong>test</strong> key. Live keys are refused here so a test
                    checkout can never move real money.
                @endif
                Your secret key is encrypted before it is stored and is never shown again after saving.
            </p>
        </div>

        @foreach ($names as $key => $name)
            @php
                $record = $gateways->get($key);
                $isCurrentForm = old('gateway', 'paystack') === $key;
            @endphp

            <div class="rounded-2xl border border-rule bg-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-bold text-ink">{{ $name }}</h2>

                        @if (! $record)
                            <span class="mt-2 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                Not connected
                            </span>
                        @elseif ($record->last_failed_at && $record->last_failed_at->gt($record->verified_at))
                            <span class="mt-2 inline-block rounded-full bg-danger-wash px-3 py-1 text-xs font-semibold text-danger-text">
                                Key stopped working
                            </span>
                        @else
                            <span class="mt-2 inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                Connected · {{ $record->environment === 'live' ? 'Live' : 'Test' }}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($record)
                    <dl class="mt-4 grid gap-3 rounded-xl bg-surface p-4 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="text-ink-3">Secret key</dt>
                            <dd class="mt-0.5 font-mono font-semibold text-ink">{{ $record->maskedSecret() }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-3">Public key</dt>
                            <dd class="mt-0.5 truncate font-mono font-semibold text-ink">{{ $record->public_key ?: 'Not saved' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-ink-3">Last checked</dt>
                            <dd class="mt-0.5 font-semibold text-ink">
                                {{ $record->verified_at?->format('j M Y, H:i') ?? 'Never' }}
                            </dd>
                        </div>
                    </dl>

                    @if ($record->last_failed_at && $record->last_failed_at->gt($record->verified_at))
                        <p class="mt-3 text-xs text-danger-text">
                            {{ $name }} rejected this key on {{ $record->last_failed_at->format('j M Y, H:i') }}.
                            Replace it to start taking payments again.
                        </p>
                    @endif

                    @php
                        $webhookScheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
                        $webhookUrl = $webhookBase ? $webhookScheme.'://'.$webhookBase.'/webhooks/'.$key : null;
                    @endphp

                    @if ($webhookUrl)
                        <div class="mt-4 rounded-xl border border-rule bg-surface p-4">
                            <h3 class="text-[11px] font-bold uppercase tracking-wide text-ink-3">Webhook URL</h3>
                            <p class="mt-1 text-xs leading-relaxed text-ink-2">
                                Paste this into your {{ $name }} dashboard. Without it, card payments succeed but the
                                order here stays "awaiting payment", because only the webhook can settle it.
                            </p>
                            <code class="mt-2 block break-all rounded-lg bg-card px-3 py-2 font-mono text-xs text-ink">{{ $webhookUrl }}</code>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <form method="POST" action="{{ route('payments.gateways.destroy', $key) }}">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-xl px-4 py-2 text-xs font-semibold text-danger-text hover:bg-danger-wash">
                                Remove key
                            </button>
                        </form>
                    </div>

                    <details class="mt-4 rounded-xl border border-rule p-4">
                        <summary class="cursor-pointer text-xs font-semibold text-ink-2">Replace this key</summary>

                        <form method="POST" action="{{ route('payments.gateways.store') }}" class="mt-4 space-y-3" autocomplete="off">
                            @csrf
                            <input type="hidden" name="gateway" value="{{ $key }}">

                            @include('dashboard.payments.partials.key-fields', [
                                'key' => $key,
                                'record' => $record,
                                'isCurrentForm' => $isCurrentForm,
                            ])

                            <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                                Verify and replace
                            </button>
                        </form>
                    </details>
                @else
                    <p class="mt-3 text-xs leading-relaxed text-ink-2">
                        Connect {{ $name }} to take card payments. Find the keys under
                        Settings → API Keys &amp; Webhooks in your {{ $name }} dashboard.
                    </p>

                    <form method="POST" action="{{ route('payments.gateways.store') }}" class="mt-4 space-y-3" autocomplete="off">
                        @csrf
                        <input type="hidden" name="gateway" value="{{ $key }}">

                        @include('dashboard.payments.partials.key-fields', [
                            'key' => $key,
                            'record' => null,
                            'isCurrentForm' => $isCurrentForm,
                        ])

                        <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                            Verify and save
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endsection
