@props(['summary'])

@php
    $state = $summary['state'];
    $colours = match ($state) {
        'warning' => ['bar' => 'bg-warn-text', 'text' => 'text-warn-text', 'edge' => 'border-warn-edge', 'wash' => 'bg-warn-wash'],
        'exhausted' => ['bar' => 'bg-danger-text', 'text' => 'text-danger-text', 'edge' => 'border-danger-edge', 'wash' => 'bg-danger-wash'],
        default => ['bar' => 'bg-brand', 'text' => 'text-brand-text', 'edge' => 'border-brand-edge', 'wash' => 'bg-brand-wash'],
    };
@endphp

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-rule bg-card p-4']) }} aria-label="Mercho usage">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-3">Mercho usage</p>
            <p class="mt-0.5 text-sm font-bold text-ink">{{ ucfirst($summary['plan']) }} plan</p>
        </div>
        @if (! $summary['billing_enabled'])
            <span class="rounded-full border border-rule px-2 py-0.5 text-[10px] font-semibold text-ink-3">Subscriptions paused</span>
        @elseif ($summary['unlimited'])
            <span class="rounded-full border border-rule px-2 py-0.5 text-[10px] font-semibold text-ink-3">No monthly cap</span>
        @else
            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $colours['edge'] }} {{ $colours['wash'] }} {{ $colours['text'] }}">
                {{ $summary['used'] }} / {{ $summary['limit'] }}
            </span>
        @endif
    </div>

    @if ($summary['unlimited'])
        <p class="mt-3 text-xs leading-5 text-ink-2">
            {{ $summary['used'] }} {{ \Illuminate\Support\Str::plural('credit', $summary['used']) }} used this month.
            {{ $summary['billing_enabled'] ? 'Your plan has no monthly Mercho cap.' : 'Monthly credits are not enforced while subscriptions are paused.' }}
        </p>
    @else
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-rule" role="progressbar" aria-label="Mercho credits used" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $summary['percent'] }}">
            <div class="h-full rounded-full {{ $colours['bar'] }}" style="width: {{ $summary['percent'] }}%"></div>
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-ink-3">
            <span>{{ $summary['used'] }} of {{ $summary['limit'] }} used</span>
            <span>{{ $summary['remaining'] }} left</span>
        </div>

        <p class="mt-2 text-[11px] text-ink-3">
            Resets {{ \Illuminate\Support\Carbon::parse($summary['period_end'])->format('j M Y') }}.
            Up to {{ $summary['image_limit'] }} {{ \Illuminate\Support\Str::plural('photo', $summary['image_limit']) }} per Mercho request.
        </p>

        @if ($state === 'warning')
            <p class="mt-2 text-[11px] font-semibold text-warn-text">You are close to this month's Mercho limit.</p>
        @elseif ($state === 'exhausted')
            <p class="mt-2 text-[11px] font-semibold text-danger-text">This month's Mercho limit is used up. Upgrade to keep using AI.</p>
        @endif

        @if (in_array($state, ['warning', 'exhausted'], true))
            <a href="{{ route('billing.index') }}" class="mt-3 inline-flex text-[11px] font-semibold text-brand-text underline">Upgrade your plan</a>
        @endif
    @endif

    @if ($summary['tasks'] !== [])
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-rule pt-3 text-[11px] text-ink-3">
            @foreach ($summary['tasks'] as $task)
                <span>{{ $task['label'] }} <span class="font-semibold text-ink-2">{{ $task['used'] }}</span></span>
            @endforeach
        </div>
    @endif
</section>
