@extends('layouts.app')

@section('content')
    <x-ui.page-header
        title="Team"
        subtitle="Who customers can book with, and what each of them does."
    >
        <a href="{{ route('availability.index', ['staff' => null]) }}" class="rounded-xl border border-rule px-4 py-2.5 text-xs font-semibold text-ink-2 hover:bg-surface">
            Availability
        </a>
    </x-ui.page-header>

    <div class="max-w-3xl mx-auto space-y-4">
        <form method="POST" action="{{ route('staff.store') }}" enctype="multipart/form-data" class="rounded-2xl border border-rule bg-card p-6 space-y-4">
            @csrf

            <h2 class="text-[11px] uppercase tracking-wide text-ink-3">Add someone</h2>

            {{-- The team block on the storefront shows a face, so a stylist who
                 never uploads one is represented by an empty circle. --}}
            <div>
                <span class="text-xs font-semibold text-ink">Photo</span>
                <input type="file" name="photo" accept="image/*"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-xs text-ink-2 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-wash file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-text">
                <p class="mt-1 text-[11px] text-ink-2">JPG or PNG, up to 2 MB. A head and shoulders shot works best.</p>
                @error('photo') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="block">
                <span class="text-xs font-semibold text-ink">Name</span>
                <input type="text" name="name" required value="{{ old('name') }}"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                       placeholder="Chidi Okafor">
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">About them</span>
                <textarea name="bio" rows="2"
                          class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                          placeholder="Senior stylist, 12 years.">{{ old('bio') }}</textarea>
            </label>

            <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                Add to the team
            </button>
        </form>

        @forelse ($staff as $member)
            <details class="rounded-2xl border border-rule bg-card p-5">
                <summary class="flex cursor-pointer items-center justify-between gap-3">
                    @if ($member->photo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($member->photo_path) }}"
                             alt="{{ $member->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover">
                    @else
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-wash text-sm font-bold text-brand-text" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-ink">{{ $member->name }}</span>
                            @unless ($member->is_active)
                                <span class="rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold text-ink-3">Hidden</span>
                            @endunless
                        </div>
                        <p class="mt-0.5 text-xs text-ink-3">
                            {{ $member->services_count }} {{ \Illuminate\Support\Str::plural('service', $member->services_count) }}
                            &middot; {{ $member->bookings_count }} {{ \Illuminate\Support\Str::plural('booking', $member->bookings_count) }}
                        </p>
                    </div>

                    <span class="text-xs font-semibold text-brand">Edit</span>
                </summary>

                <form method="POST" action="{{ route('staff.update', $member) }}" enctype="multipart/form-data" class="mt-4 space-y-4 border-t border-rule pt-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <span class="text-xs font-semibold text-ink">Photo</span>

                        @if ($member->photo_path)
                            <div class="mt-2 flex items-center gap-4">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($member->photo_path) }}"
                                     alt="{{ $member->name }}" class="h-20 w-20 rounded-full object-cover">
                                <label class="flex items-center gap-2 text-xs text-ink-2">
                                    <input type="checkbox" name="remove_photo" value="1" class="rounded border-rule-strong">
                                    Remove this photo
                                </label>
                            </div>
                        @endif

                        <input type="file" name="photo" accept="image/*"
                               class="mt-2 w-full rounded-xl border border-rule px-3 py-2 text-xs text-ink-2 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-wash file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-text">
                        @error('photo') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Name</span>
                        <input type="text" name="name" required value="{{ $member->name }}"
                               class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">About them</span>
                        <textarea name="bio" rows="2"
                                  class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">{{ $member->bio }}</textarea>
                    </label>

                    @if ($services->isNotEmpty())
                        <fieldset>
                            <legend class="text-xs font-semibold text-ink">What they do</legend>
                            <p class="mt-0.5 text-[11px] text-ink-3">
                                Tick everything they can take. Untick all to let anyone free take it.
                            </p>

                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($services as $id => $name)
                                    <label class="flex items-center gap-2 text-sm text-ink">
                                        <input type="checkbox" name="services[]" value="{{ $id }}"
                                               @checked($member->services->pluck('id')->contains($id))
                                               class="rounded border-rule-strong">
                                        {{ $name }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <label class="flex items-center gap-2.5 text-sm text-ink">
                        <input type="checkbox" name="is_active" value="1" @checked($member->is_active) class="rounded border-rule-strong">
                        Show on my public page
                    </label>

                    <div class="flex items-center gap-3">
                        <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">Save</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('staff.destroy', $member) }}" class="mt-3 border-t border-rule pt-3"
                      onsubmit="return confirm('Remove {{ $member->name }}? Their bookings are kept.')">
                    @csrf
                    @method('DELETE')
                    <button class="text-[11px] font-semibold text-ink-3 hover:text-danger-text">Remove from the team</button>
                </form>
            </details>
        @empty
            <div class="rounded-2xl border border-rule bg-card px-6 py-14 text-center">
                <p class="text-sm text-ink-3">No one on the team yet.</p>
                <p class="mt-1 text-xs text-ink-3">
                    This is optional. Without staff, every booking goes to whoever is free.
                </p>
            </div>
        @endforelse

        @if ($staff->hasPages())
            <div class="mt-4">{{ $staff->links() }}</div>
        @endif
    </div>
@endsection