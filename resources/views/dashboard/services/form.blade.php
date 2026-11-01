@extends('layouts.app')

@section('content')
    @php
        $editing = $service !== null;
        // The form talks in naira; the model only ever stores kobo.
        $price = old('price', $editing ? $service->price_kobo / 100 : null);
        $depositValue = old('deposit_value', $editing
            ? ($service->deposit_type?->value === 'percent' ? $service->deposit_value : $service->deposit_value / 100)
            : null);
        $compareAt = old('compare_at', $editing && $service->compare_at_kobo ? $service->compare_at_kobo / 100 : null);
        $saleStartsAt = old('sale_starts_at', $editing ? $service->sale_starts_at?->format('Y-m-d\TH:i') : null);
        $saleEndsAt = old('sale_ends_at', $editing ? $service->sale_ends_at?->format('Y-m-d\TH:i') : null);
    @endphp

    <x-ui.page-header
        :title="$editing ? 'Edit service' : 'Add a service'"
        subtitle="How long it takes and what it costs."
        :back="route('services.index')"
        back-label="Back to services"
        width="max-w-2xl mx-auto"
    />

    <form method="POST" action="{{ $editing ? route('services.update', $service) : route('services.store') }}"
          enctype="multipart/form-data"
          class="max-w-2xl mx-auto space-y-4"
          x-data="aiListingForm(@jsExpr([
              'name' => old('name', $service?->name ?? ''),
              'description' => old('description', $service?->description ?? ''),
              'task' => 'service_listing',
              'feature' => 'services',
              'endpoint' => route('dashboard.ai.run'),
              'csrf' => csrf_token(),
          ]))">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="bg-card rounded-2xl border border-rule p-6 space-y-4">
            {{-- A service with no photo shows up on the listing as an empty tile,
                 and the services page is the first thing a salon owner looks at.
                 One square image per service, not a gallery. --}}
            <div>
                <span class="text-xs font-semibold text-ink">Photo</span>

                @if ($editing && $service->image_path)
                    <div class="mt-2 flex items-center gap-4">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($service->image_path) }}"
                             alt="{{ $service->name }}" class="h-24 w-24 rounded-xl object-cover">
                        <label class="flex items-center gap-2 text-xs text-ink-2">
                            <input type="checkbox" name="remove_image" value="1" class="rounded border-rule-strong">
                            Remove this photo
                        </label>
                    </div>
                @endif

                <input type="file" name="image" accept="image/*"
                       class="mt-2 w-full rounded-xl border border-rule px-3 py-2 text-xs text-ink-2 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-wash file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-text">
                <p class="mt-1 text-[11px] text-ink-2">JPG or PNG, up to 4 MB. Square works best.</p>
                @error('image') <p class="text-danger-text text-[11px] mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="block">
                <span class="mb-1 flex items-center justify-between gap-3 text-xs font-semibold text-ink">
                    <span>Name</span>
                    <button type="button" @click.prevent="draftCopy" :disabled="aiLoading || !name && !description" class="rounded-lg border border-brand-edge bg-brand-wash px-2.5 py-1.5 text-[11px] font-bold text-brand-text disabled:opacity-50"><span x-text="aiLoading ? 'Drafting…' : 'Draft with Mercho'"></span></button>
                </span>
                <input type="text" name="name" required x-model="name"
                       class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                       placeholder="Braid consultation">
            </label>

            <label class="block">
                <span class="text-xs font-semibold text-ink">Description</span>
                <textarea name="description" rows="4" x-model="description"
                          class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                          placeholder="What the appointment covers, and anything the customer should bring.">{{ old('description', $service?->description) }}</textarea>
                <p x-show="aiMessage" x-text="aiMessage" class="mt-2 text-[11px] text-ok-text"></p>
                <p x-show="aiError" x-text="aiError" class="mt-2 text-[11px] text-danger-text"></p>
            </label>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-4">Length and price</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">Duration (minutes)</span>
                    <input type="number" name="duration_min" required min="5" max="1440" step="5"
                           value="{{ old('duration_min', $service?->duration_min ?? 60) }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Reset time after</span>
                    <input type="number" name="buffer_min" min="0" max="240" step="5"
                           value="{{ old('buffer_min', $service?->buffer_min ?? 0) }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    <span class="mt-1 block text-[11px] text-ink-3">Time you need to tidy up before the next customer.</span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Price (₦)</span>
                    <input type="number" name="price" min="0" step="0.01"
                           value="{{ $price }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                           placeholder="0.00">
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">That price is</span>
                    <select name="billing_unit"
                            class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                        @foreach (\App\Domains\Booking\Enums\BillingUnit::cases() as $unit)
                            <option value="{{ $unit->value }}"
                                @selected(old('billing_unit', $service?->billing_unit?->value ?? 'per_project') === $unit->value)>
                                {{ $unit->label() }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-[11px] text-ink-3">
                        Shown on the card as ₦25,000 {{ old('billing_unit', $service?->billing_unit?->value ?? 'per_project') === 'per_hour' ? '/hour' : (old('billing_unit', $service?->billing_unit?->value ?? 'per_project') === 'per_month' ? '/month' : '/project') }}.
                        A price with no unit is a number a customer has to email about.
                    </span>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">How long until they have it</span>
                    <input type="text" name="turnaround_label" maxlength="60"
                           value="{{ old('turnaround_label', $service?->turnaround_label) }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                           placeholder="5 working days">
                    <span class="mt-1 block text-[11px] text-ink-3">
                        Your words, not a number. "Same day", "2 weeks", "by the end of the month".
                    </span>
                </label>
            </div>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">Sale pricing</h2>
            <p class="text-xs text-ink-3 mb-3">
                The price above is what the customer pays. Enter your normal price here to show it crossed out
                while the sale runs. Leave the window blank to keep it on until you remove the original price.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">Original price (₦)</span>
                    <input type="number" name="compare_at" min="0" step="0.01"
                           value="{{ $compareAt }}"
                           placeholder="Leave blank for no sale"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    @error('compare_at') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Starts</span>
                    <input type="datetime-local" name="sale_starts_at"
                           value="{{ $saleStartsAt }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    <span class="mt-1 block text-[11px] text-ink-3">Blank means already started.</span>
                    @error('sale_starts_at') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Ends</span>
                    <input type="datetime-local" name="sale_ends_at"
                           value="{{ $saleEndsAt }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    <span class="mt-1 block text-[11px] text-ink-3">Blank means no end date.</span>
                    @error('sale_ends_at') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                </label>
            </div>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">What is included</h2>
            <p class="text-xs text-ink-3 mb-3">
                One per line. These become the tick-list on your service card, and they are how a
                customer tells two of your tiers apart without emailing you.
            </p>

            <textarea name="deliverables" rows="4" maxlength="1000"
                      class="w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none"
                      placeholder="Consultation and plan&#10;The work itself&#10;Aftercare advice">{{ old('deliverables', $service ? implode("\n", $service->deliverableList()) : '') }}</textarea>

            <span class="mt-1 block text-[11px] text-ink-3">
                The first four are shown; the rest are counted but not listed.
            </span>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-4">Deposit</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">Customer pays</span>
                    <select name="deposit_type"
                            class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                        @foreach (\App\Domains\Booking\Enums\DepositType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('deposit_type', $service?->deposit_type?->value ?? 'none') === $type->value)>
                                {{ $type->value === 'none' ? 'The full price' : ($type->value === 'fixed' ? 'A fixed amount' : 'A percentage') }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Amount</span>
                    <input type="number" name="deposit_value" min="0" step="0.01" value="{{ $depositValue }}"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    <span class="mt-1 block text-[11px] text-ink-3">
                        In naira for a fixed deposit, whole percent for a percentage one.
                    </span>
                </label>
            </div>
        </div>

        <div class="bg-card rounded-2xl border border-rule p-6">
            <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">Where you deliver it</h2>
            <p class="text-xs text-ink-3 mb-3">
                A haircut happens at your place; a make-up booking might happen at theirs. Set this
                per service so the right option is offered when someone books.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-xs font-semibold text-ink">This service is</span>
                    <select name="service_mode"
                            class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                        <option value="" @selected(old('service_mode', $service?->service_mode?->value ?? '') === '')>Use my store settings</option>
                        <option value="at_location" @selected(old('service_mode', $service?->service_mode?->value) === 'at_location')>At my place</option>
                        <option value="home" @selected(old('service_mode', $service?->service_mode?->value) === 'home')>I travel to them</option>
                        <option value="remote" @selected(old('service_mode', $service?->service_mode?->value) === 'remote')>Remote / online</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Travel fee (₦)</span>
                    <input type="number" name="travel_fee" min="0" step="0.01" inputmode="decimal"
                           value="{{ old('travel_fee', $service?->travel_fee_kobo ? number_format($service->travel_fee_kobo / 100, 2, '.', '') : '') }}"
                           placeholder="Leave blank for free"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-ink">Travel radius (km)</span>
                    <input type="number" name="travel_radius_km" min="0" max="1000" inputmode="numeric"
                           value="{{ old('travel_radius_km', $service?->travel_radius_km) }}"
                           placeholder="e.g. 15"
                           class="mt-1 w-full rounded-xl border border-rule px-3 py-2 text-sm focus:border-brand focus:outline-none">
                    <span class="mt-1 block text-[11px] text-ink-3">How far you will travel for this service.</span>
                </label>
            </div>

            @error('service_mode') <p class="mt-2 text-[11px] text-danger-text">{{ $message }}</p> @enderror
            @error('travel_fee') <p class="mt-2 text-[11px] text-danger-text">{{ $message }}</p> @enderror
            @error('travel_radius_km') <p class="mt-2 text-[11px] text-danger-text">{{ $message }}</p> @enderror
        </div>

        {{-- The staff list arrives as id => name, an array. A collection method
             on it is a fatal, and the form is the only place that renders it. --}}        @if (! empty($staff))
            <div class="bg-card rounded-2xl border border-rule p-6">
                <h2 class="text-[11px] uppercase tracking-wide text-ink-3 mb-2">Who can do this</h2>
                <p class="text-xs text-ink-3 mb-3">Leave all unticked to let anyone free take the booking.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($staff as $id => $name)
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" name="staff[]" value="{{ $id }}"
                                   @checked(in_array($id, old('staff', $editing ? $service->staff->pluck('id')->all() : [])))>
                            {{ $name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        {{--
            Two switches, not one. "Offer this service" is about whether it can be
            booked; "Put it first" is about how it is presented. A seller pausing a
            service must not silently lose its prominence, and a seller promoting
            one must not accidentally publish it.
        --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-center gap-2.5 rounded-2xl bg-card border border-rule p-4 text-sm text-ink">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service?->is_active ?? true))>
                Offer this service on my page
            </label>

            <label class="flex items-start gap-2.5 rounded-2xl bg-card border border-rule p-4 text-sm text-ink">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" class="mt-0.5"
                       @checked(old('is_featured', $service?->is_featured ?? false))>
                <span>
                    Put this one first
                    <span class="mt-0.5 block text-[11px] text-ink-3">
                        Adds a Featured ribbon and keeps it above the others on your services page.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button class="rounded-xl bg-brand px-5 py-2.5 text-xs font-semibold text-white hover:bg-brand-hover">
                {{ $editing ? 'Save service' : 'Add service' }}
            </button>
            <a href="{{ route('services.index') }}" class="text-xs font-semibold text-ink-3">Cancel</a>
        </div>
    </form>
@endsection
