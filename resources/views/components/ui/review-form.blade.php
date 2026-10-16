@props([
    'action',
    'reviewable' => null,
    'subject' => null,
])

@php
    /**
     * The public "leave a review" form.
     *
     * Buyers have no account on this platform and that is deliberate — browsing
     * and buying must not need a sign-in — so this asks for a name instead. That
     * is why it is a review form and not a login form, and why everything on it
     * is short: three fields and five stars.
     *
     * The star input is real radio buttons. Five clickable stars built from
     * divs would need their own keyboard model, their own focus handling and
     * their own screen-reader narration, and would still be worse than the
     * browser's. So: radios, visually replaced, natively focusable.
     */
    $ratings = [
        5 => ['label' => 'Excellent', 'hint' => 'I would come back'],
        4 => ['label' => 'Good', 'hint' => 'Happy with it'],
        3 => ['label' => 'Fine', 'hint' => 'It did the job'],
        2 => ['label' => 'Poor', 'hint' => 'I expected better'],
        1 => ['label' => 'Bad', 'hint' => 'I would not recommend'],
    ];
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
         aria-labelledby="review-form-heading">

    <h2 id="review-form-heading" class="font-display text-lg font-extrabold tracking-tight text-ink dark:text-slate-100">
        Leave a review
    </h2>

    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
        @if ($subject)
            About <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $subject }}</span>.
        @endif
        {{ $page->name }} reads every review before publishing it.
    </p>

    {{-- Errors are announced, not just coloured: a form that fails silently
         teaches people to submit twice. --}}
    @if ($errors->any())
        <div role="alert"
             class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('review_sent'))
        <div role="status"
             class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('review_sent') }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}" x-data="{ rating: 0, hover: 0 }"
          class="mt-5 flex flex-col gap-4">

        @csrf

        {{-- The stars. One radio, visually replaced by the row of icons below.
             `sr-only` keeps the radio in the accessibility tree and the tab order;
             the icons are aria-hidden and pointer-events-none so a click lands on
             the radio underneath. --}}
        <fieldset>
            <legend class="text-xs font-semibold text-ink dark:text-slate-200">
                Your rating <span class="font-normal text-red-500" aria-hidden="true">*</span>
            </legend>

            <div class="mt-2 flex items-center gap-0.5" @mouseleave="hover = 0">
                @foreach ($ratings as $value => $meta)
                    <label class="relative cursor-pointer p-0.5" @mouseenter="hover = {{ $value }}">
                        <input type="radio" name="rating" value="{{ $value }}" required
                               x-model.number="rating"
                               class="peer sr-only">

                        {{-- pointer-events-none so the click reaches the radio
                             underneath it rather than the icon. --}}
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"
                             aria-hidden="true" class="pointer-events-none transition-colors duration-150
                                    peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-amber-400"
                             :class="(hover >= {{ $value }} || rating >= {{ $value }})
                                 ? 'text-amber-400'
                                 : 'text-slate-300 dark:text-slate-700'">
                            <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z"/>
                        </svg>

                        {{-- The name is on the label, not on the icon: a screen
                             reader gets "4 stars, Good — Happy with it". --}}
                        <span class="sr-only">{{ $value }} {{ $meta['label'] }} — {{ $meta['hint'] }}</span>
                    </label>
                @endforeach
            </div>

            @error('rating')
                <p class="mt-1.5 text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold text-ink dark:text-slate-200">Your name</span>
                <input type="text" name="author_name" required maxlength="80"
                       value="{{ old('author_name') }}"
                       autocomplete="name"
                       placeholder="Chidi Okeke"
                       class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                @error('author_name')
                    <span class="mt-1 block text-[11px] text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink dark:text-slate-200">
                    Where you are <span class="font-normal text-slate-400">(optional)</span>
                </span>
                <input type="text" name="author_location" maxlength="80"
                       value="{{ old('author_location') }}"
                       placeholder="Lekki"
                       class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                @error('author_location')
                    <span class="mt-1 block text-[11px] text-red-600 dark:text-red-400">{{ $message }}</span>
                @enderror
            </label>
        </div>

        <label class="block">
            <span class="text-xs font-semibold text-ink dark:text-slate-200">Your review</span>
            <textarea name="body" rows="4" required minlength="10" maxlength="2000"
                      placeholder="What was it like? What should the next person know?"
                      class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">{{ old('body') }}</textarea>
            <span class="mt-1 block text-[11px] text-slate-400 dark:text-slate-500">
                What you write is shown to everyone. Names and messages are checked
                before they appear.
            </span>
            @error('body')
                <span class="mt-1 block text-[11px] text-red-600 dark:text-red-400">{{ $message }}</span>
            @enderror
        </label>

        <label class="flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="is_anonymous" value="1"
                   @checked(old('is_anonymous'))
                   class="mt-0.5 size-4 rounded border-slate-300 accent-brand">
            <span>
                Show my review as <strong class="font-semibold">“A customer”</strong>
                instead of my name.
            </span>
        </label>

        <div>
            <button type="submit"
                    class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand px-6 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 hover:bg-brand-hover disabled:opacity-50 sm:w-auto"
                    x-data="{ sending: false }"
                    x-on:click="sending = true"
                    x-bind:disabled="sending">
                <svg x-show="sending" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" aria-hidden="true" class="animate-spin">
                    <path stroke-linecap="round" d="M21 12a9 9 0 11-6.22-8.56" />
                </svg>
                <span x-text="sending ? 'Sending…' : 'Submit review'">Submit review</span>
            </button>
        </div>
    </form>
</section>