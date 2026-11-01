@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Your domain"
        subtitle="Use a domain you own instead of your mypage.ng address."
        width="max-w-3xl mx-auto"
    />

    <div class="max-w-3xl mx-auto space-y-4">
        {{-- Upgrade prompt. Shown instead of the form, not as an error: the
             feature works, it is part of a plan this shop is not on. --}}
        @unless ($eligible)
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">A {{ $requiredPlan }} plan includes your own domain</h2>
                <p class="mt-1 text-xs leading-relaxed text-ink-3">
                    On {{ $requiredPlan }} you can point a domain you already own at your shop.
                    You keep using your mypage.ng address either way, so nothing breaks if you change plan later.
                </p>

                <a href="{{ route('billing.index') }}"
                   class="mt-4 inline-block rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">
                    See plans
                </a>
            </div>
        @else
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Add a domain</h2>
                <p class="mt-1 text-xs text-ink-3">
                    Enter the domain you own, then create the CNAME record below at your domain provider.
                </p>

                <form method="POST" action="{{ route('domains.store') }}" class="mt-4 space-y-4">
                    @csrf

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Your domain</span>
                        <input type="text" name="domain" value="{{ old('domain') }}"
                               placeholder="shop.com" required
                               class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        <span class="mt-1 block text-[11px] text-ink-3">
                            Just the domain. We will add https:// and point it at your shop.
                        </span>
                        @error('domain') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </label>

                    <button class="rounded-xl bg-brand px-6 py-3 text-sm font-semibold text-white hover:bg-brand-hover">
                        Add this domain
                    </button>
                </form>
            </div>
        @endunless

        @forelse ($bindings as $binding)
            <div class="rounded-2xl border border-rule bg-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="font-semibold text-ink">{{ $binding->host }}</p>
                        <span class="mt-1.5 inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $binding->status->badgeClasses() }}">
                            {{ $binding->status->label() }}
                        </span>
                    </div>

                    <div class="flex gap-2">
                        @if (! $binding->isServed())
                            <form method="POST" action="{{ route('domains.verify', $binding) }}">
                                @csrf
                                <button class="rounded-xl border border-rule px-4 py-2 text-xs font-semibold text-ink-2 hover:bg-surface">
                                    Check again
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('domains.destroy', $binding) }}">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-xl px-4 py-2 text-xs font-semibold text-danger-text hover:bg-danger-wash">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>

                @if ($binding->isServed())
                    <p class="mt-4 text-xs text-ink-3">
                        Your shop is now at
                        <a href="https://{{ $binding->host }}" target="_blank" rel="noopener" class="underline">
                            {{ $binding->host }}
                        </a>.
                        Your mypage.ng address keeps working.
                    </p>
                @else
                    {{-- The record the seller has to create. Shown as literal
                         text, not a form field: copying it must not be possible
                         to get subtly wrong. --}}
                    <div class="mt-4 rounded-xl bg-surface p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-3">At your domain provider, add</p>

                        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                            <div>
                                <dt class="text-ink-3">Type</dt>
                                <dd class="font-semibold text-ink">CNAME</dd>
                            </div>
                            <div>
                                <dt class="text-ink-3">Name / host</dt>
                                <dd class="font-semibold text-ink">{{ $binding->host }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-ink-3">Points to</dt>
                                <dd class="font-semibold text-ink">{{ $cnameTarget }}</dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-[11px] text-ink-3">
                            DNS can take a few hours to catch up. We keep checking for you.
                        </p>
                    </div>

                    @if ($binding->last_error)
                        <p class="mt-3 text-xs text-warn-text">{{ $binding->nextStep() }}</p>
                    @endif
                @endif
            </div>
        @empty
            @if ($eligible)
                <p class="rounded-2xl border border-dashed border-rule px-6 py-10 text-center text-sm text-ink-3">
                    You have not added a domain yet.
                </p>
            @endif
        @endforelse

        @if ($bindings->hasPages())
            <div>{{ $bindings->links() }}</div>
        @endif
    </div>
@endsection