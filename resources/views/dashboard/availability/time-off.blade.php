@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Dates off"
        subtitle="Holidays, training days and the shop being shut. These block slots on top of your weekly hours."
        :back="route('availability.index', ['staff' => null])"
        back-label="Back to availability"
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        <form method="POST" action="{{ route('time-off.store') }}" class="rounded-2xl border border-rule bg-card p-6 space-y-4"
              x-data="{ allDay: true }">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">From</span>
                    <input type="date" name="starts_on" required value="{{ old('starts_on') }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Until</span>
                    <input type="date" name="ends_on" required value="{{ old('ends_on') }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </label>
            </div>

            <label class="flex items-center gap-2.5 text-sm text-ink">
                <input type="hidden" name="all_day" value="0">
                <input type="checkbox" name="all_day" value="1" x-model="allDay" @checked(old('all_day', true)) class="rounded border-rule-strong">
                Closed all day
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="! allDay">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">From time</span>
                    <input type="time" name="start_time" value="{{ old('start_time', '09:00') }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Until time</span>
                    <input type="time" name="end_time" value="{{ old('end_time', '17:00') }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </label>
            </div>

            @if ($staffOptions->isNotEmpty())
                <label class="block">
                    <span class="text-xs font-semibold text-ink">Who is off</span>
                    <select name="staff_member_id"
                            class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                        <option value="">The whole shop</option>
                        @foreach ($staffOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }} only</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-[11px] text-ink-3">Leave this on the whole shop unless only one person is away.</span>
                </label>
            @endif

            <label class="block">
                <span class="text-xs font-semibold text-ink">Reason</span>
                <input type="text" name="reason" value="{{ old('reason') }}"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                       placeholder="Public holiday">
            </label>

            <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                Block these dates
            </button>
        </form>

        <div class="rounded-2xl border border-rule bg-card overflow-hidden">
            @forelse ($blocks as $block)
                <div class="flex flex-wrap items-center gap-3 border-b border-rule px-4 py-3 last:border-0">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink">
                            {{ $block->starts_at->timezone($timezone)->format('D j M') }}
                            @if ($block->starts_at->timezone($timezone)->toDateString() !== $block->ends_at->timezone($timezone)->toDateString())
                                &ndash; {{ $block->ends_at->timezone($timezone)->format('D j M') }}
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-ink-3">
                            {{ $block->staffMember?->name ?? 'The whole shop' }}
                            @if ($block->reason)
                                &middot; {{ $block->reason }}
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('time-off.destroy', $block) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-[11px] font-semibold text-ink-3 hover:text-danger-text">Remove</button>
                    </form>
                </div>
            @empty
                <div class="px-6 py-14 text-center">
                    <p class="text-sm text-ink-3">Nothing blocked off.</p>
                    <p class="mt-1 text-xs text-ink-3">Add holidays here so customers cannot book you on them.</p>
                </div>
            @endforelse
        </div>

        @if ($blocks->hasPages())
            <div>{{ $blocks->links() }}</div>
        @endif
    </div>
@endsection