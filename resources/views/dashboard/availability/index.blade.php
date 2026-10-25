@extends('layouts.app')

@section('content')
    @php
        $weekdayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        // Built here rather than inline in the attribute below. A double-quoted
        // string with escaped quotes inside :subtitle="..." closes the HTML
        // attribute early, and the rest of the component tag is then printed on
        // the page as plain text.
        $subtitle = $staff
            ? "When {$staff->name} is free. Leave a day off here and {$staff->name} works your shop hours instead."
            : 'When customers can book you. Nothing is bookable until at least one day is open.';
    @endphp

    <x-ui.page-header
        title="Availability"
        :subtitle="$subtitle"
        :back="$staff ? route('availability.index', ['staff' => null]) : null"
        :back-label="$staff ? 'Shop hours' : null"
        width="max-w-3xl mx-auto"
    />

    {{-- Kept outside the header slot: an @if opened inside a component slot
         does not survive Blade's component compilation. --}}
    @if ($staffOptions->isNotEmpty())
        <div class="max-w-3xl mx-auto mb-4 flex items-center gap-2">
            <label for="staff-picker" class="text-xs font-semibold text-ink-2">Editing hours for</label>
            <select id="staff-picker"
                    onchange="window.location = this.value"
                    class="rounded-xl border border-rule bg-card px-3 py-2 text-xs focus:border-brand focus:outline-none">
                <option value="{{ route('availability.index', ['staff' => null]) }}" @selected(! $staff)>The whole shop</option>
                @foreach ($staffOptions as $option)
                    <option value="{{ route('availability.index', ['staff' => $option->id]) }}" @selected($staff?->id === $option->id)>
                        {{ $option->name }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <form method="POST"
          action="{{ $staff ? route('availability.update', ['staff' => $staff->id]) : route('availability.update', ['staff' => null]) }}"
          class="max-w-3xl mx-auto space-y-4"
          x-data="availabilityEditor(@jsExpr(['days' => $week]))">
        @csrf
        {{-- The route is PUT (and DELETE beside it for time off), so the form
             posts with method spoofing. Without this the browser's POST never
             reaches the handler and Laravel answers 405. --}}
        @method('PUT')

        <div class="rounded-2xl border border-rule bg-card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <p class="text-[11px] uppercase tracking-wide text-ink-3">
                    Times are in {{ $timezone }}
                </p>

                <div class="flex items-center gap-2">
                    <label class="text-[11px] text-ink-3" for="copy-from">Copy hours from</label>
                    <select id="copy-from" x-model="copyFrom"
                            class="rounded-lg border border-rule px-2 py-1.5 text-[11px] focus:border-brand focus:outline-none">
                        <option value="">Choose a day…</option>
                        @foreach ($weekdayNames as $number => $label)
                            <option value="{{ $number }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" @click="copy"
                            class="rounded-lg border border-rule px-2.5 py-1.5 text-[11px] font-semibold text-ink-2 hover:bg-surface">
                        Apply to all
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                @foreach ($weekdayNames as $number => $label)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-rule px-4 py-3">
                        <label class="flex w-40 shrink-0 items-center gap-2.5">
                            <input type="hidden" name="days[{{ $number }}][enabled]" value="0">
                            <input type="checkbox" name="days[{{ $number }}][enabled]" value="1"
                                   x-model="days[{{ $number }}].enabled"
                                   @checked(old("days.{$number}.enabled", $week[$number]['enabled']))
                                   class="rounded border-rule-strong">
                            <span class="text-sm font-semibold text-ink">{{ $label }}</span>
                        </label>

                        {{--
                            Bracket keys, not dots: Alpine evaluates this as a
                            JavaScript expression, and `days.1.enabled` is a
                            syntax error — the row stayed hidden and untickable
                            because the binding never applied. The style is bound
                            rather than written into the tag so opening a day
                            from the server's closed state keeps the flex row.
                        --}}
                        <div class="flex items-center gap-2"
                             style="display: {{ $week[$number]['enabled'] ? 'flex' : 'none' }}"
                             :style="days[{{ $number }}].enabled ? 'display: flex' : 'display: none'">
                            <input type="time" name="days[{{ $number }}][start]"
                                   x-model="days[{{ $number }}].start"
                                   value="{{ old("days.{$number}.start", $week[$number]['start']) }}"
                                   class="rounded-lg border border-rule px-2.5 py-1.5 text-xs focus:border-brand focus:outline-none">
                            <span class="text-xs text-ink-3">to</span>
                            <input type="time" name="days[{{ $number }}][end]"
                                   x-model="days[{{ $number }}].end"
                                   value="{{ old("days.{$number}.end", $week[$number]['end']) }}"
                                   class="rounded-lg border border-rule px-2.5 py-1.5 text-xs focus:border-brand focus:outline-none">
                        </div>

                        <span class="text-[11px] text-ink-3"
                              :style="days[{{ $number }}].enabled ? 'display: none' : 'display: inline'">Closed</span>

                        @if ($errors->has("days.{$number}.start"))
                            <p class="text-[11px] text-danger-text">{{ $errors->first("days.{$number}.start") }}</p>
                        @endif
                        @if ($errors->has("days.{$number}.end"))
                            <p class="text-[11px] text-danger-text">{{ $errors->first("days.{$number}.end") }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                Save hours
            </button>
            <span class="text-[11px] text-ink-3">
                Bookings already confirmed are not moved by changing these.
            </span>
        </div>
    </form>
@endsection