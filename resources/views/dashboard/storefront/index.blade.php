@extends('layouts.app')

@section('content')
    @php $siteNoun = $page->theme->siteNoun(); @endphp

    <x-ui.page-header
        title="Your {{ $siteNoun }}"
        subtitle="Change the words and pictures on your pages. Everything left blank simply does not appear."
        width="max-w-3xl mx-auto"
    />

    {{-- Tabs: the page settings first, then one per editable page. --}}
    @php
        $allTabs = ['brand' => 'Brand', 'details' => 'Details', 'delivery' => 'Delivery'] + $tabs;

        if ($discoveryOn) {
            $allTabs['listing'] = 'Directory';
        }
    @endphp

    @if (count($allTabs) > 1)
        <div class="mx-auto mb-6 flex max-w-3xl gap-1 overflow-x-auto rounded-xl bg-tint p-1">
            @foreach ($allTabs as $key => $label)
                <a href="{{ route('storefront.edit', ['tab' => $key]) }}"
                   class="flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold transition-colors {{ $tab === $key ? 'bg-card text-ink shadow-sm' : 'text-ink-3 hover:text-ink' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('storefront.update', $tab) }}" enctype="multipart/form-data"
          class="mx-auto max-w-3xl space-y-4"
          x-data="storefrontEditor(@jsExpr([
              'endpoint' => route('dashboard.ai.contextual'),
              'csrf' => csrf_token(),
              'feature' => $tab === 'brand' ? 'appearance' : 'content',
              'text' => 'Describe the look that suits '.$page->name.' for a '.$page->businessType().' business.',
              'preview' => $merchoPreview,
              'approveUrl' => route('dashboard.ai.drafts.approve', ['draft' => '__DRAFT__']),
              'discardUrl' => route('dashboard.ai.drafts.discard', ['draft' => '__DRAFT__']),
          ]))">
        @csrf

        @if ($tab !== 'delivery')
            <div class="rounded-2xl border border-brand-edge bg-brand-wash/50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-ink">Mercho for this page</p>
                        <p class="mt-1 text-[11px] text-ink-3">
                            @if ($tab === 'brand')
                                Get a complete storefront direction: theme, colours, layout and the words for every page. Review it before anything is applied.
                            @else
                                Get a copy suggestion for the {{ $allTabs[$tab] ?? $tab }} page. Review and paste only what you want to keep.
                            @endif
                        </p>
                    </div>
                    <button type="button" @click="draftPageCopy" :disabled="aiLoading" class="min-h-10 rounded-xl bg-brand px-3.5 text-xs font-bold text-brand-ink disabled:opacity-50">
                        <span x-text="aiLoading ? 'Preparing…' : 'Improve with Mercho'"></span>
                    </button>
                </div>
                <p x-show="aiLoading" x-cloak class="mt-3 text-[11px] text-ink-3">
                    Mercho is preparing {{ $tab === 'brand' ? 'a complete storefront direction' : 'a copy suggestion' }}. Review it before anything is applied.
                </p>
                <p x-show="aiMessage" x-cloak class="mt-2 text-[11px] font-semibold text-ok-text" x-text="aiMessage"></p>
                <p x-show="aiError" x-cloak x-text="aiError" class="mt-2 text-[11px] text-danger-text"></p>
            </div>
        @endif

        @if ($tab === 'details')
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Business details</h2>
                <p class="mt-1 text-xs text-ink-3">
                    How customers reach you. Shown on your contact page, in the directory and
                    wherever your business is listed.
                </p>

                <div class="mt-5 space-y-4">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Tagline</span>
                        <input type="text" name="tagline" value="{{ old('tagline', $page->tagline) }}"
                               maxlength="160" placeholder="One line about what you {{ $page->theme === \App\Domains\Seller\Enums\Theme::Service ? 'do' : 'sell' }}"
                               class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">WhatsApp number</span>
                            <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $page->whatsapp_number) }}"
                                   placeholder="0803 123 4567"
                                   class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            @error('whatsapp_number') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold text-ink">Email address</span>
                            <input type="email" name="public_email" value="{{ old('public_email', $page->public_email) }}"
                                   placeholder="you@example.com"
                                   class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            <span class="mt-1 block text-[11px] text-ink-3">Shown publicly. Leave blank if you would rather not.</span>
                            @error('public_email') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Street address</span>
                        <input type="text" name="address" value="{{ old('address', $page->address) }}"
                               placeholder="22b Oduduwa Crescent, Egbeda"
                               class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        @error('address') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </label>

                    <div class="rounded-2xl border border-brand-edge bg-brand-wash/40 p-4" data-map-picker>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-xs font-bold text-ink">Pin your exact location</h3>
                                <p class="mt-1 max-w-xl text-[11px] leading-relaxed text-ink-3">
                                    Search for your shop, choose a result, then drag the pin or tap the map to correct it.
                                    Customers will see this location on your contact page.
                                </p>
                            </div>
                            @php
                                $mapSelection = \App\Shared\Support\MapProvider::selection();
                                $hasGoogleKey = \App\Shared\Support\MapProvider::googleKey() !== null;
                                $useGoogle = \App\Shared\Support\MapProvider::isGoogle();
                            @endphp
                            @if ($useGoogle)
                                <span class="rounded-full bg-card px-2.5 py-1 text-[10px] font-semibold text-brand-text">Google Maps</span>
                            @else
                                <span class="rounded-full bg-card px-2.5 py-1 text-[10px] font-semibold text-ink-2">OpenStreetMap</span>
                            @endif
                        </div>

                        @if ($useGoogle)
                            <label class="mt-4 block">
                                <span class="text-[11px] font-semibold text-ink">Search the map</span>
                                <div id="location-search" class="mt-1 min-h-11 overflow-hidden rounded-xl border border-rule-strong bg-card"></div>
                            </label>

                            <div id="location-map" class="mt-3 h-72 overflow-hidden rounded-xl border border-rule-strong bg-tint" aria-label="Business location map"></div>
                            <p class="mt-2 text-[11px] text-ink-3">The pin is the source of truth. The address above remains editable for customers who need the written directions.</p>
                        @else
                            <label class="mt-4 block">
                                <span class="text-[11px] font-semibold text-ink">Search the map</span>
                                <input id="osm-location-search" type="text" class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2 text-sm focus:border-brand focus:outline-none" placeholder="Search an address, landmark or business">
                                <div id="osm-search-results" class="mt-2 space-y-1"></div>
                            </label>

                            <div id="location-map-osm" class="mt-3 h-72 overflow-hidden rounded-xl border border-rule-strong bg-tint" aria-label="Business location map"></div>
                            <p class="mt-2 text-[11px] text-ink-3">The pin is the source of truth. The address above remains editable for customers who need the written directions.</p>
                            @if ($mapSelection === 'google' && ! $hasGoogleKey)
                                <p class="mt-2 rounded-xl border border-warn-edge bg-warn-wash px-3 py-2.5 text-[11px] leading-relaxed text-warn-text">
                                    Google Maps was selected but no <code class="font-semibold">GOOGLE_MAPS_BROWSER_KEY</code> is configured — falling back to OpenStreetMap.
                                </p>
                            @endif
                        @endif

                        <input type="hidden" name="latitude" id="location-latitude" value="{{ old('latitude', $page->latitude) }}">
                        <input type="hidden" name="longitude" id="location-longitude" value="{{ old('longitude', $page->longitude) }}">
                        @error('latitude') <p class="mt-2 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                        @error('longitude') <p class="mt-2 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">City</span>
                            <input type="text" name="city" value="{{ old('city', $page->city) }}" required
                                   class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            @error('city') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold text-ink">State</span>
                            <input type="text" name="state" value="{{ old('state', $page->state) }}" required
                                   class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            @error('state') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                        </label>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Social media</h2>
                <p class="mt-1 text-xs text-ink-3">
                    Just the handle, not the whole link. Leave blank for any you do not use.
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach ($socialPlatforms as $platform)
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">
                                {{ \App\Shared\Support\SocialLinks::label($platform) }}
                            </span>
                            <input type="text"
                                   name="social[{{ $platform }}]"
                                   value="{{ old('social.'.$platform, \Illuminate\Support\Str::after((string) ($page->social_links[$platform] ?? ''), 'https://')) }}"
                                   placeholder="@yourhandle"
                                   maxlength="190"
                                   class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        </label>
                    @endforeach
                </div>

                <p class="mt-4 text-[11px] text-ink-3">
                    These appear in the footer of your page and tell search engines where else
                    to find you.
                </p>
            </div>
        @endif

        @if ($tab === 'delivery')
            @php
                $zoneRows = ($deliveryProfile?->zones ?? collect())
                    ->map(fn ($zone): array => [
                        'name' => $zone->name,
                        'state' => (string) $zone->state,
                        'fee' => $zone->fee_kobo > 0 ? number_format($zone->fee_kobo / 100, 2, '.', '') : '',
                    ])
                    ->values()
                    ->all();

                if ($zoneRows === []) {
                    $zoneRows = [['name' => '', 'state' => '', 'fee' => '']];
                }
            @endphp

            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Delivery &amp; pickup</h2>
                <p class="mt-1 text-xs leading-relaxed text-ink-3">
                    Store-wide defaults. Each product or service can still override the fee
                    or opt out on its own form.
                </p>

                <div class="mt-5 space-y-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
                        <input type="checkbox" name="offers_delivery" value="1" class="mt-0.5 rounded" @checked(old('offers_delivery', $deliveryProfile?->offers_delivery))>
                        <span>
                            <span class="block text-xs font-semibold text-ink">Deliver orders</span>
                            <span class="mt-1 block text-[11px] text-ink-3">Customers choose an area at checkout and pay that area's fee.</span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
                        <input type="checkbox" name="offers_pickup" value="1" class="mt-0.5 rounded" @checked(old('offers_pickup', $deliveryProfile?->offers_pickup))>
                        <span>
                            <span class="block text-xs font-semibold text-ink">Allow pickup</span>
                            <span class="mt-1 block text-[11px] text-ink-3">Customers collect from you at no delivery fee.</span>
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
                        <input type="checkbox" name="offers_home_service" value="1" class="mt-0.5 rounded" @checked(old('offers_home_service', $deliveryProfile?->offers_home_service))>
                        <span>
                            <span class="block text-xs font-semibold text-ink">Offer home service</span>
                            <span class="mt-1 block text-[11px] text-ink-3">For services you travel to the customer. Add the travel fee on each service.</span>
                        </span>
                    </label>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Free delivery over (₦)</span>
                        <input type="number" name="free_over" min="0" step="0.01" inputmode="decimal"
                               value="{{ old('free_over', $deliveryProfile?->free_over_kobo ? number_format($deliveryProfile->free_over_kobo / 100, 2, '.', '') : '') }}"
                               placeholder="Leave blank for no threshold"
                               class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        @error('free_over') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold text-ink">Service radius (km)</span>
                        <input type="number" name="radius_km" min="0" max="1000" inputmode="numeric"
                               value="{{ old('radius_km', $deliveryProfile?->radius_km) }}"
                               placeholder="e.g. 15"
                               class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        <span class="mt-1 block text-[11px] text-ink-3">How far you travel for home service. Shown to customers.</span>
                        @error('radius_km') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-semibold text-ink">Delivery note</span>
                        <input type="text" name="notes" maxlength="255"
                               value="{{ old('notes', $deliveryProfile?->notes) }}"
                               placeholder="e.g. Same-day within Lagos, next day outside"
                               class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        @error('notes') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                    </label>
                </div>
            </div>

            <div class="rounded-2xl border border-rule bg-card p-6"
                 x-data="deliveryZoneForm(@jsExpr(['rows' => $zoneRows]))">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-ink">Delivery areas</h2>
                        <p class="mt-1 text-xs leading-relaxed text-ink-3">
                            The places you deliver to and what you charge. Use the town or LGA
                            people would recognise, not a full address.
                        </p>
                    </div>
                    <button type="button" @click="addRow()" class="min-h-10 rounded-xl border border-rule-strong px-3.5 text-xs font-bold text-ink">
                        Add area
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="grid gap-3 sm:grid-cols-[1.4fr_1fr_0.8fr_auto] sm:items-end">
                            <label class="block">
                                <span class="text-[11px] font-semibold text-ink">Area</span>
                                <input type="text" :name="'zones['+index+'][name]'" x-model="row.name" maxlength="80"
                                       placeholder="e.g. Ikeja"
                                       class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            </label>

                            <label class="block">
                                <span class="text-[11px] font-semibold text-ink">State</span>
                                <input type="text" :name="'zones['+index+'][state]'" x-model="row.state" maxlength="80"
                                       placeholder="e.g. Lagos"
                                       class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            </label>

                            <label class="block">
                                <span class="text-[11px] font-semibold text-ink">Fee (₦)</span>
                                <input type="number" :name="'zones['+index+'][fee]'" x-model="row.fee" min="0" step="0.01" inputmode="decimal"
                                       placeholder="0 = free"
                                       class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            </label>

                            <button type="button" @click="removeRow(index)" class="min-h-10 rounded-xl px-3 text-[11px] font-bold text-danger-text hover:bg-danger-wash">
                                Remove
                            </button>
                        </div>
                    </template>
                </div>

                <p class="mt-4 text-[11px] text-ink-3">
                    Leave the fee at 0 to make that area free. Areas left blank are ignored
                    when you save.
                </p>

                @error('zones') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                @error('zones.*.name') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
                @error('zones.*.fee') <p class="mt-1 text-[11px] text-danger-text">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($tab === 'listing')
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Directory listing</h2>

                <p class="mt-1 text-xs leading-relaxed text-ink-3">
                    The mypage.ng directory is where people look when they do not know which
                    shop or which person they want yet. Turning this on puts
                    {{ $page->name }} in that search.
                </p>

                {{--
                    A switch, not a save-on-click. The platform has no other
                    switch-shaped control and introducing the first one here would
                    mean a seller who taps "on" and then reloads the page to find
                    it did not save.
                --}}
                <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
                    <input type="hidden" name="is_directory_opted_in" value="0">
                    <input type="checkbox" name="is_directory_opted_in" value="1"
                           @checked(old('is_directory_opted_in', $page->is_directory_opted_in))
                           class="mt-0.5 size-4 rounded border-rule-strong accent-brand">
                    <span>
                        <span class="block text-sm font-semibold text-ink">List me in the directory</span>
                        <span class="mt-0.5 block text-xs text-ink-3">
                            Your name, tagline, city, cover and what you sell or offer.
                            Your contact details and your customers' details are never shown.
                        </span>
                    </span>
                </label>

                @if (! $page->isPublished())
                    <p class="mt-4 rounded-xl bg-warn-wash px-4 py-3 text-xs text-warn-text">
                        Your page is not published yet, so it will not appear in the directory
                        even with this on.
                    </p>
                @endif

                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
                    <input type="hidden" name="reduce_motion" value="0">
                    <input type="checkbox" name="reduce_motion" value="1" @checked(data_get(old(), 'reduce_motion', data_get($page->settings, 'reduce_motion', false))) class="mt-0.5 size-4 rounded border-rule-strong accent-brand">
                    <span><span class="block text-sm font-semibold text-ink">Reduce storefront motion</span><span class="mt-0.5 block text-xs text-ink-3">Keep the page calm for visitors who prefer less animation.</span></span>
                </label>

                <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-rule p-4 hover:border-rule-strong">
                    <input type="hidden" name="animated_background" value="0">
                    <input type="checkbox" name="animated_background" value="1" @checked(data_get(old(), 'animated_background', data_get($page->settings, 'animated_background', false))) class="mt-0.5 size-4 rounded border-rule-strong accent-brand">
                    <span><span class="block text-sm font-semibold text-ink">Animated storefront background</span><span class="mt-0.5 block text-xs text-ink-3">Add a quiet, brand-coloured moving layer behind your theme. It respects visitors’ reduced-motion settings.</span></span>
                </label>

                <div class="mt-6 border-t border-rule pt-6">
                    <label for="price_tier" class="text-sm font-bold text-ink">Where do your prices sit?</label>
                    <p class="mt-1 text-xs text-ink-3">
                        A rough answer, used as a filter. Buyers narrow by this before they read
                        anything else, and guessing wrong puts you in the wrong search.
                    </p>

                    <select id="price_tier" name="price_tier"
                            class="mt-3 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                        <option value="">Not set</option>
                        @foreach ($priceTiers as $tier)
                            <option value="{{ $tier->value }}"
                                @selected(old('price_tier', $page->price_tier?->value) === $tier->value)>
                                {{ $tier->label() }} — {{ $tier->description() }}
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-4 text-[11px] text-ink-3">
                        Your rating and review count are not editable here. They come from what
                        buyers write about you, and a rating you typed yourself would not be worth
                        anything to the next person who reads it.
                    </p>
                </div>
            </div>
        @endif

        @if ($tab === 'brand')
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Brand colour</h2>
                <p class="mt-1 text-xs text-ink-3">
                    Used for buttons and links across your whole page. Every other colour on the
                    page — washes, hairlines, button text, the focus ring — is derived from this
                    one and checked for contrast, so you only ever choose the one.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <input type="color" name="brand_color" value="{{ old('brand_color', $page->brand_color) }}"
                           class="h-11 w-20 cursor-pointer rounded-xl border border-rule-strong">
                    <span class="text-xs text-ink-3">Your colour, everywhere on your page.</span>
                </div>
                @error('brand_color') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
            </div>

            {{--
                The three presets. The layout itself is fixed by the theme — a
                seller does not drag sections — but how the fixed layout is
                finished is theirs, and these are the three choices that decide
                it. Each one re-derives every token that depends on it.
            --}}
            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Look &amp; feel</h2>
                <p class="mt-1 text-xs text-ink-3">
                    Three choices that change how the whole page reads. Your layout does not move.
                </p>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label for="theme_preset" class="text-xs font-semibold text-ink">Theme direction</label>
                        <select id="theme_preset" name="theme_preset" class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            @foreach (config('themes.presets') as $key => $preset)
                                @if (in_array($page->theme->value, $preset['business_types'], true))
                                    <option value="{{ $key }}" @selected($brandSettings['theme_preset'] === $key)>{{ $preset['label'] }} — {{ $preset['description'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-ink-3">A locked layout direction with its own rhythm, type and density.</p>
                    </div>

                    <div>
                        <label for="brand_surface" class="text-xs font-semibold text-ink">Surface</label>
                        <select id="brand_surface" name="surface"
                                class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            @foreach (config('theming.modes') as $key => $mode)
                                <option value="{{ $key }}" @selected($brandSettings['surface'] === $key)>
                                    {{ \Illuminate\Support\Str::headline($key) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-ink-3">Light or dark neutrals behind everything.</p>
                    </div>

                    <div>
                        <label for="brand_radius" class="text-xs font-semibold text-ink">Corner style</label>
                        <select id="brand_radius" name="radius"
                                class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                            @foreach (config('theming.radii') as $key => $px)
                                <option value="{{ $key }}" @selected($brandSettings['radius'] === $key)>
                                    {{ \Illuminate\Support\Str::headline($key) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-ink-3">One number moves every corner on the page.</p>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-ink">Typeface</span>
                        <div class="mt-1 flex min-h-11 items-center rounded-xl border border-rule bg-tint px-3 py-2.5 text-sm text-ink">
                            {{ config('themes.presets.'.$brandSettings['theme_preset'].'.font_label', 'Theme default') }}
                        </div>
                        <p class="mt-1 text-[11px] text-ink-3">{{ $brandFontNote }}</p>
                    </div>
                </div>

                @error('surface') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
                @error('radius') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
                @error('font') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
                @error('theme_preset') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Logo</h2>
                <p class="mt-1 text-xs text-ink-3">Shows in the directory listing and at the top of your page.</p>

                <div class="mt-4 flex items-start gap-4">
                    @if ($page->logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($page->logo_path) }}"
                             alt="" class="h-20 w-20 rounded-xl border border-rule object-cover">
                    @endif

                    <div class="flex-1">
                        <input type="file" name="logo" accept="image/*" class="text-xs">
                        @if ($page->logo_path)
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-ink-3">
                                <input type="checkbox" name="remove_logo" value="1" class="rounded">
                                Remove this logo
                            </label>
                        @endif
                    </div>
                </div>
                @error('logo') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Cover photo</h2>
                <p class="mt-1 text-xs text-ink-3">
                    A wide photo across the top of your page. Leave it out and nothing shows there.
                </p>

                <div class="mt-4">
                    @if ($page->cover_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($page->cover_path) }}"
                             alt="" class="mb-3 h-28 w-full rounded-xl border border-rule object-cover">
                    @endif

                    <input type="file" name="cover" accept="image/*" class="text-xs">
                    @if ($page->cover_path)
                        <label class="mt-2 flex items-center gap-2 text-[11px] text-ink-3">
                            <input type="checkbox" name="remove_cover" value="1" class="rounded">
                            Remove this photo
                        </label>
                    @endif
                </div>
                @error('cover') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-2xl border border-rule bg-card p-6">
                <h2 class="text-sm font-bold text-ink">Social sharing image</h2>
                <p class="mt-1 text-xs text-ink-3">This image appears when your storefront is shared on WhatsApp, Facebook, or X. Use a wide image with your business name visible.</p>
                <div class="mt-4">
                    @if ($page->og_image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($page->og_image_path) }}" alt="" class="mb-3 h-28 w-full rounded-xl border border-rule object-cover">
                    @endif
                    <input type="file" name="og_image" accept="image/jpeg,image/png,image/webp" class="text-xs">
                    @if ($page->og_image_path)
                        <label class="mt-2 flex items-center gap-2 text-[11px] text-ink-3"><input type="checkbox" name="remove_og_image" value="1" class="rounded"> Remove sharing image</label>
                    @endif
                </div>
                @error('og_image') <p class="mt-2 text-xs text-danger-text">{{ $message }}</p> @enderror
            </div>

            @if ($heroStylesEnabled)
                <div class="rounded-2xl border border-rule bg-card p-6">
                    <h2 class="text-sm font-bold text-ink">Hero direction</h2>
                    <p class="mt-1 text-xs leading-relaxed text-ink-3">
                        Give the first screen a stronger point of view. These controls
                        change the composition, not your content.
                    </p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">Layout</span>
                            <select name="hero_style" class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm">
                                @foreach (['immersive' => 'Immersive image', 'split' => 'Editorial split', 'minimal' => 'Minimal statement'] as $key => $label)
                                    <option value="{{ $key }}" @selected(($page->settings['hero_style'] ?? 'immersive') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">Text alignment</span>
                            <select name="hero_alignment" class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm">
                                <option value="center" @selected(($page->settings['hero_alignment'] ?? 'center') === 'center')>Centered</option>
                                <option value="left" @selected(($page->settings['hero_alignment'] ?? 'center') === 'left')>Left aligned</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-ink">Image treatment</span>
                            <select name="hero_overlay" class="mt-1 w-full rounded-xl border border-rule-strong bg-card px-3 py-2.5 text-sm">
                                <option value="deep" @selected(($page->settings['hero_overlay'] ?? 'deep') === 'deep')>Deep contrast</option>
                                <option value="soft" @selected(($page->settings['hero_overlay'] ?? 'deep') === 'soft')>Soft contrast</option>
                                <option value="none" @selected(($page->settings['hero_overlay'] ?? 'deep') === 'none')>Clean image</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-xl border border-rule p-3">
                            <input type="hidden" name="carousel_autoplay" value="0">
                            <input type="checkbox" name="carousel_autoplay" value="1" @checked($page->settings['carousel_autoplay'] ?? false) class="mt-0.5 size-4 accent-brand">
                            <span><span class="block text-xs font-semibold">Auto-advance carousel</span><span class="mt-1 block text-[11px] text-ink-3">Keep it off for a calmer editorial feel.</span></span>
                        </label>
                        <label class="flex items-start gap-3 rounded-xl border border-rule p-3">
                            <input type="hidden" name="show_trust_strip" value="0">
                            <input type="checkbox" name="show_trust_strip" value="1" @checked($page->settings['show_trust_strip'] ?? true) class="mt-0.5 size-4 accent-brand">
                            <span><span class="block text-xs font-semibold">Show trust strip</span><span class="mt-1 block text-[11px] text-ink-3">Show platform assurances beneath the hero.</span></span>
                        </label>
                    </div>

                    @if ($layoutOptions->isNotEmpty())
                        <div class="mt-6 border-t border-rule pt-5">
                            <h3 class="text-xs font-bold text-ink">Page layout</h3>
                            <p class="mt-1 text-[11px] text-ink-3">Hide sections you do not need. Required catalog or booking sections stay available.</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($layoutOptions as $key => $label)
                                    <label class="flex items-center gap-2 rounded-lg border border-rule px-3 py-2.5 text-xs text-ink-2">
                                        <input type="checkbox" name="hidden_sections[]" value="{{ $key }}"
                                               @checked(in_array($key, $page->settings['hidden_sections']['home'] ?? [], true))
                                               class="size-4 accent-brand">
                                        Hide {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        @endif

        @foreach ($blocks as $block)
            @php
                $schema = $block->schema();
                $fields = $schema['fields'] ?? [];
            @endphp

            @if ($fields === [])
                @continue
            @endif

            <div class="rounded-2xl border border-rule bg-card p-6">
                <div class="mb-4">
                    <h2 class="text-sm font-bold text-ink">{{ $schema['label'] ?? $block->label() }}</h2>
                    @if (! empty($schema['help']))
                        <p class="mt-1 text-xs text-ink-3">{{ $schema['help'] }}</p>
                    @endif
                </div>

                <div class="space-y-4">
                    @foreach ($fields as $name => $field)
                        @php $value = $block->content[$name] ?? null; @endphp

                        @if (($field['type'] ?? 'text') === 'richtext')
                            <label class="block">
                                <span class="flex items-center justify-between gap-2 text-xs font-semibold text-ink"><span>{{ $field['label'] }}</span><button type="button" @click="openAiForField($event)" class="text-[11px] font-bold text-brand-text">Improve with Mercho</button></span>
                                <textarea name="blocks[{{ $block->key }}][{{ $name }}]" rows="6" @ai-result.window="if ($event.detail.feature === 'content') $el.value = $event.detail.text"
                                          class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2 text-sm focus:border-brand focus:outline-none">{{ old($name, $value) }}</textarea>
                            </label>

                        @elseif (($field['type'] ?? '') === 'image')
                            <div>
                                <span class="text-xs font-semibold text-ink">{{ $field['label'] }}</span>
                                @if ($value)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($value) }}"
                                         alt="" class="mt-2 h-24 w-auto rounded-lg object-cover">
                                @endif
                                <input type="file" name="blocks[{{ $block->key }}][{{ $name }}]" accept="image/*"
                                       class="mt-2 text-xs">
                                <p class="mt-1 text-[11px] text-ink-3">JPG or PNG, up to 4 MB. Square images look best.</p>
                            </div>

                        @elseif (($field['type'] ?? '') === 'repeater')
                            {{--
                                One hidden input per populated row per sub-field, plus
                                textareas bound to the live array. Rows with nothing
                                in them are submitted as blanks and dropped on save,
                                so removing a row from the form is enough.
                            --}}
                            @php
                                $rows = old($name, $value ?? []);
                                $rows = is_array($rows) ? $rows : [];
                                $subFields = $field['fields'] ?? [];
                            @endphp

                            <div class="space-y-2">
                                <span class="text-xs font-semibold text-ink">{{ $field['label'] }}</span>

                                @foreach ($rows as $index => $row)
                                    <div class="rounded-xl border border-rule p-4">
                                        @foreach ($subFields as $subName => $subField)
                                            <label class="block {{ $loop->first ? '' : 'mt-2' }}">
                                                <span class="text-[11px] font-semibold text-ink-2">{{ $subField['label'] }}</span>
                                                <textarea rows="2"
                                                          name="blocks[{{ $block->key }}][{{ $name }}][{{ $index }}][{{ $subName }}]"
                                                          class="mt-1 w-full rounded-lg border border-rule-strong px-3 py-2 text-sm">{{ $row[$subName] ?? '' }}</textarea>
                                            </label>
                                        @endforeach
                                        <button type="button"
                                                onclick="this.closest('.rounded-xl').remove()"
                                                class="mt-2 text-[11px] font-semibold text-danger-text">Remove</button>
                                    </div>
                                @endforeach

                                {{--
                                    Adding a row appends a group of textareas with the
                                    next free index. Plain JS on purpose: the form has
                                    to work without Alpine, and repeaters are the one
                                    place a framework would have added a build step for
                                    six lines of DOM.
                                --}}
                                {{-- Blank row the add button clones. --}}
                                <template id="repeater-{{ $block->key }}">
                                    <div data-row class="rounded-xl border border-rule p-4">
                                        @foreach ($subFields as $subName => $subField)
                                            <label class="block {{ $loop->first ? '' : 'mt-2' }}">
                                                <span class="text-[11px] font-semibold text-ink-2">{{ $subField['label'] }}</span>
                                                <textarea rows="2"
                                                          name="blocks[{{ $block->key }}][{{ $name }}][__INDEX__][{{ $subName }}]"
                                                          class="mt-1 w-full rounded-lg border border-rule-strong px-3 py-2 text-sm"></textarea>
                                            </label>
                                        @endforeach
                                        <button type="button" onclick="this.closest('[data-row]').remove()"
                                                class="mt-2 text-[11px] font-semibold text-danger-text">Remove</button>
                                    </div>
                                </template>

                                <div class="repeater space-y-2" data-block="{{ $block->key }}">
                                    <button type="button" data-add
                                            class="rounded-lg border border-rule px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface">
                                        Add {{ strtolower($field['label']) }}
                                    </button>
                                </div>
                            </div>

                        @elseif ($name === 'hours')
                            <div x-data="hoursEditor(@jsExpr([
                                'value' => old($name, $value),
                                'placeholder' => $field['placeholder'] ?? 'Monday to Friday, 9am - 6pm',
                            ]))">
                                <span class="text-xs font-semibold text-ink">{{ $field['label'] }}</span>
                                <p class="mt-1 text-[11px] text-ink-3">Add one row for each set of opening times.</p>

                                <div class="mt-2 space-y-2">
                                    <template x-for="(row, index) in rows" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="rows[index]" :placeholder="placeholder"
                                                   class="min-h-10 w-full rounded-xl border border-rule-strong bg-card px-3 text-sm focus:border-brand focus:outline-none">
                                            <button type="button" @click="remove(index)"
                                                    class="min-h-10 shrink-0 rounded-xl border border-rule-strong px-3 text-xs font-bold text-danger-text">Remove</button>
                                        </div>
                                    </template>
                                </div>

                                <button type="button" @click="add()"
                                        class="mt-2 rounded-lg border border-rule px-3 py-2 text-xs font-semibold text-ink-2 hover:bg-surface">
                                    Add time
                                </button>

                                <input type="hidden" name="blocks[{{ $block->key }}][{{ $name }}]" value="{{ old($name, $value) }}" :value="joined">
                            </div>

                        @elseif (($field['type'] ?? '') === 'textarea')
                            <label class="block">
                                <span class="text-xs font-semibold text-ink">{{ $field['label'] }}</span>
                                <textarea rows="3"
                                          name="blocks[{{ $block->key }}][{{ $name }}]"
                                          @if (! empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                                          class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">{{ old($name, $value) }}</textarea>
                            </label>

                        @else
                            <label class="block">
                                <span class="text-xs font-semibold text-ink">{{ $field['label'] }}</span>
                                <input type="text"
                                       name="blocks[{{ $block->key }}][{{ $name }}]"
                                       value="{{ old($name, $value) }}"
                                       @if (! empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                                       class="mt-1 w-full rounded-xl border border-rule-strong px-3 py-2.5 text-sm focus:border-brand focus:outline-none">
                                @if (! empty($field['default']) && $value === null)
                                    <span class="mt-1 block text-[11px] text-ink-3">
                                        Leave blank to use "{{ $field['default'] }}".
                                    </span>
                                @endif
                            </label>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Mercho review modal. Nothing is applied until the seller chooses a
             direction or approves the suggestion. --}}
        <div x-show="aiOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-label="Review Mercho suggestion">
            <div class="absolute inset-0 bg-ink/45" @click="closeAi()"></div>
            <section class="relative flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[1.75rem] border border-rule bg-card text-ink shadow-2xl sm:rounded-2xl">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-rule px-5 py-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink-3">Mercho suggestion</p>
                        <h2 class="mt-1 text-lg font-bold" x-text="hasDirection ? 'Review your storefront direction' : (hasPresets ? 'Choose a visual direction' : 'Review this copy')"></h2>
                        <p class="mt-1 text-xs text-ink-3" x-text="hasDirection ? 'One direction for the whole storefront. Edit any words you want to change, then apply it. You can change everything again later.' : (hasPresets ? 'Preview each direction, then apply the one you want. You can change it again later.' : 'Read the suggestion, copy what you want to keep, then paste it into your fields.')"></p>
                    </div>
                    <button type="button" @click="closeAi()" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl text-ink-3 hover:bg-tint hover:text-ink" aria-label="Close review">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                    <p x-show="aiError" x-cloak role="alert" class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="aiError"></p>

                    <div x-show="hasDirection" x-cloak class="space-y-4">
                        <div class="rounded-2xl border border-rule bg-surface p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-ink-3">Look &amp; feel</p>
                                    <p class="mt-1 text-sm font-bold text-ink" x-text="directionThemeLabel"></p>
                                    <p class="mt-1 text-xs leading-5 text-ink-2" x-text="aiDraft.reason"></p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="size-8 rounded-full border border-rule" :style="'background:' + aiDraft.brand_color" :title="aiDraft.brand_color"></span>
                                    <span class="size-8 rounded-full border border-rule" :style="'background:' + aiDraft.accent_color" :title="aiDraft.accent_color"></span>
                                </div>
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-3">
                                <div><dt class="text-ink-3">Surface</dt><dd class="font-semibold text-ink" x-text="directionSurfaceLabel"></dd></div>
                                <div><dt class="text-ink-3">Corners</dt><dd class="font-semibold text-ink" x-text="directionRadiusLabel"></dd></div>
                                <div><dt class="text-ink-3">Hero</dt><dd class="font-semibold text-ink" x-text="directionHeroStyleLabel"></dd></div>
                                <div><dt class="text-ink-3">Alignment</dt><dd class="font-semibold text-ink" x-text="directionHeroAlignmentLabel"></dd></div>
                                <div><dt class="text-ink-3">Image</dt><dd class="font-semibold text-ink" x-text="directionHeroOverlayLabel"></dd></div>
                                <div><dt class="text-ink-3">Carousel</dt><dd class="font-semibold text-ink" x-text="aiDraft.carousel_autoplay ? 'Auto-advance' : 'Manual'"></dd></div>
                                <div><dt class="text-ink-3">Trust strip</dt><dd class="font-semibold text-ink" x-text="aiDraft.show_trust_strip ? 'Shown' : 'Hidden'"></dd></div>
                                <div><dt class="text-ink-3">Metrics</dt><dd class="font-semibold text-ink" x-text="aiDraft.show_metrics ? 'Shown' : 'Hidden'"></dd></div>
                            </dl>

                            <div x-show="directionHiddenLabels.length > 0" class="mt-3 border-t border-rule pt-3">
                                <p class="text-[11px] font-semibold text-ink-3">Hidden sections</p>
                                <p class="mt-1 text-xs text-ink-2" x-text="directionHiddenLabels.join(', ')"></p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <template x-for="page in copySections" :key="page.key">
                                <details open class="rounded-2xl border border-rule bg-card p-4">
                                    <summary class="cursor-pointer text-sm font-bold text-ink" x-text="page.label + ' page'"></summary>

                                    <div class="mt-4 space-y-3">
                                        <template x-for="block in page.blocks" :key="block.key">
                                            <div class="rounded-xl border border-rule bg-surface p-3">
                                                <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3" x-text="block.label"></p>

                                                <div class="mt-2 space-y-2">
                                                    <template x-for="field in block.fields" :key="field.name">
                                                        <div>
                                                            <span class="text-[11px] font-semibold text-ink-2" x-text="field.label"></span>

                                                            <template x-if="field.type === 'text'">
                                                                <input type="text" maxlength="500" x-model="aiDraft.copy[page.key][block.key][field.name]" class="mt-1 w-full rounded-lg border border-rule-strong bg-card px-3 py-2 text-sm text-ink focus:border-brand focus:outline-none">
                                                            </template>

                                                            <template x-if="field.type !== 'text' && field.type !== 'repeater'">
                                                                <textarea rows="3" x-model="aiDraft.copy[page.key][block.key][field.name]" class="mt-1 w-full rounded-lg border border-rule-strong bg-card px-3 py-2 text-sm leading-6 text-ink focus:border-brand focus:outline-none"></textarea>
                                                            </template>

                                                            <template x-if="field.type === 'repeater'">
                                                                <div class="mt-2 space-y-2">
                                                                    <template x-for="(row, rowIndex) in repeaterRows(page.key, block.key, field.name)" :key="rowIndex">
                                                                        <div class="rounded-lg border border-rule bg-card p-2">
                                                                            <template x-for="sub in field.fields" :key="sub.name">
                                                                                <label class="block">
                                                                                    <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-ink-3" x-text="sub.label"></span>
                                                                                    <textarea rows="2" x-model="aiDraft.copy[page.key][block.key][field.name][rowIndex][sub.name]" class="mt-1 w-full rounded-lg border border-rule-strong bg-card px-2 py-1.5 text-xs leading-5 text-ink focus:border-brand focus:outline-none"></textarea>
                                                                                </label>
                                                                            </template>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </details>
                            </template>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-brand-edge bg-brand-wash/40 p-3">
                            <p class="text-[11px] leading-5 text-ink-3">
                                Mercho never writes reviews, prices, stock or policies. Check every word and every hidden section before you apply.
                            </p>
                            <button type="button" @click="approveDraft()" :disabled="aiBusy || !aiDraftId" class="min-h-10 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50">Apply this direction</button>
                        </div>
                    </div>

                    <div x-show="hasPresets" class="space-y-3">
                        <template x-for="(preset, index) in aiPresets" :key="index">
                            <article class="rounded-2xl border border-rule bg-surface p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-ink" x-text="'Preset ' + (index + 1) + ' · ' + preset.theme_key"></p>
                                        <p class="mt-1 text-xs leading-5 text-ink-2" x-text="preset.reason"></p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="size-8 rounded-full border border-rule" :style="'background:' + preset.brand_color" :title="preset.brand_color"></span>
                                        <span class="size-8 rounded-full border border-rule" :style="'background:' + preset.accent_color" :title="preset.accent_color"></span>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-[11px] text-ink-3"><span x-text="preset.brand_color"></span> · <span x-text="preset.accent_color"></span></p>
                                    <button type="button" @click="applyPreset(index)" :disabled="aiBusy" class="min-h-10 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50">Apply this direction</button>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div x-show="!hasPresets && !hasDirection" class="space-y-3">
                        <textarea readonly rows="12" :value="aiText" class="w-full rounded-xl border border-rule-strong bg-surface p-3 text-sm leading-6 text-ink"></textarea>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" @click="copyAiText()" class="min-h-10 rounded-xl border border-rule-strong px-3 text-xs font-bold text-ink-2">Copy text</button>
                            <button type="button" @click="approveDraft()" :disabled="aiBusy" class="min-h-10 rounded-xl bg-brand px-4 text-xs font-bold text-brand-ink disabled:opacity-50">Approve draft</button>
                        </div>
                    </div>
                </div>

                <footer class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-rule px-5 py-4">
                    <button type="button" @click="discardDraft()" :disabled="aiBusy || !aiDraftId" class="min-h-10 rounded-xl border border-rule-strong px-3 text-xs font-bold text-ink-2 disabled:opacity-50">Discard draft</button>
                    <button type="button" @click="closeAi()" class="min-h-10 rounded-xl border border-rule-strong px-3 text-xs font-bold text-ink-2">Keep editing</button>
                </footer>
            </section>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="rounded-xl bg-brand px-6 py-3 text-sm font-semibold text-white hover:bg-brand-hover">
                Save {{ $siteNoun }}
            </button>
            <a href="{{ \App\Shared\Support\StoreUrl::forSeller($page) }}" target="_blank" rel="noopener"
               class="text-xs font-semibold text-ink-3 hover:text-ink">View my page</a>
        </div>
    </form>

    @if ($tab === 'brand' && $carouselEnabled)
        <section class="mx-auto mt-6 max-w-3xl rounded-2xl border border-rule bg-card p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-ink">Hero carousel</h2>
                    <p class="mt-1 max-w-xl text-xs leading-relaxed text-ink-3">
                        Add up to five campaign moments. Visitors can swipe between
                        them on mobile; the first slide is your opening statement.
                    </p>
                </div>
                <span class="rounded-full bg-brand-wash px-3 py-1 text-[11px] font-semibold text-brand-text">{{ $carouselSlides->count() }}/5 slides</span>
            </div>

            <div class="mt-5 space-y-3">
                @foreach ($carouselSlides as $slide)
                    <form method="POST" action="{{ route('carousel.update', $slide) }}" enctype="multipart/form-data"
                          class="grid gap-4 rounded-xl border border-rule p-4 md:grid-cols-[7rem_minmax(0,1fr)_auto]">
                        @csrf
                        @method('PUT')
                        @if ($slide->image_path)
                            <img src="{{ $slide->imageUrl() }}" alt="" class="h-20 w-28 rounded-lg object-cover">
                        @else
                            <div class="flex h-20 w-28 items-center justify-center rounded-lg bg-tint text-[10px] text-ink-3">No image</div>
                        @endif
                        <div class="grid gap-2 sm:grid-cols-2">
                            <input name="headline" value="{{ $slide->headline }}" placeholder="Slide headline" required class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                            <input name="subtext" value="{{ $slide->subtext }}" placeholder="Supporting line" class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                            <input name="button_label" value="{{ $slide->button_label }}" placeholder="Button label" class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                            <input type="url" name="button_url" value="{{ $slide->button_url }}" placeholder="https://..." class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                            <input type="file" name="image" accept="image/*" class="text-[11px] sm:col-span-2">
                            <label class="flex items-center gap-2 text-[11px] text-ink-3 sm:col-span-2">
                                <input type="checkbox" name="is_active" value="1" @checked($slide->is_active) class="size-4 accent-brand"> Visible on the storefront
                            </label>
                        </div>
                        <div class="flex gap-2 md:flex-col">
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('carousel.move', ['slide' => $slide, 'direction' => 'up']) }}">@csrf<button aria-label="Move slide up" class="rounded-lg border border-rule px-2 py-2 text-[11px] font-bold text-ink-2">↑</button></form>
                                <form method="POST" action="{{ route('carousel.move', ['slide' => $slide, 'direction' => 'down']) }}">@csrf<button aria-label="Move slide down" class="rounded-lg border border-rule px-2 py-2 text-[11px] font-bold text-ink-2">↓</button></form>
                            </div>
                            <button class="rounded-lg bg-brand px-3 py-2 text-[11px] font-bold text-brand-ink">Save</button>
                            <button type="submit" name="remove" value="1" data-confirm="Remove this slide?" class="rounded-lg border border-danger-edge px-3 py-2 text-[11px] font-semibold text-danger-text">Remove</button>
                        </div>
                    </form>
                @endforeach
            </div>

            @if ($carouselSlides->count() < 5)
                <form method="POST" action="{{ route('carousel.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-3 rounded-xl border border-dashed border-rule-strong p-4 sm:grid-cols-2">
                    @csrf
                    <input name="headline" placeholder="New slide headline" required class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                    <input name="subtext" placeholder="Supporting line" class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                    <input name="button_label" placeholder="Button label" class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                    <input type="url" name="button_url" placeholder="https://..." class="rounded-lg border border-rule-strong px-3 py-2 text-xs">
                    <input type="file" name="image" accept="image/*" required class="text-[11px] sm:col-span-2">
                    <button class="rounded-lg border border-rule-strong px-3 py-2 text-xs font-semibold hover:bg-tint sm:col-span-2">Add slide</button>
                </form>
            @endif
        </section>
    @endif

    @once
        <script>
            // Repeater rows: appended server-side templates, no framework needed.
            document.querySelectorAll('[data-add]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const wrap = button.closest('.repeater');
                    const block = wrap.dataset.block;
                    const template = document.getElementById('repeater-' + block);

                    if (!template) return;

                    const next = wrap.querySelectorAll('[data-row]').length;

                    wrap.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, next));
                });
            });
        </script>
    @endonce
@endsection

@if ($tab === 'details')
    @php
        $useGoogle = \App\Shared\Support\MapProvider::isGoogle();
    @endphp
    @section('scripts')
        @if ($useGoogle)
            <script>
                window.initLocationPicker = async function () {
                    const mapElement = document.getElementById('location-map');
                    const searchElement = document.getElementById('location-search');
                    const latitudeElement = document.getElementById('location-latitude');
                    const longitudeElement = document.getElementById('location-longitude');

                    if (!mapElement || !searchElement || !latitudeElement || !longitudeElement) {
                        return;
                    }

                    const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');

                    const fallback = { lat: 6.5244, lng: 3.3792 };
                    const initial = {
                        lat: Number(latitudeElement.value) || fallback.lat,
                        lng: Number(longitudeElement.value) || fallback.lng,
                    };
                    const map = new google.maps.Map(mapElement, {
                        center: initial,
                        zoom: latitudeElement.value && longitudeElement.value ? 16 : 11,
                        mapTypeControl: false,
                        streetViewControl: false,
                        fullscreenControl: false,
                    });
                    const marker = new google.maps.Marker({
                        map,
                        position: initial,
                        draggable: true,
                        title: 'Business location',
                    });

                    const setLocation = (location, zoom = 16) => {
                        const lat = typeof location.lat === 'function' ? location.lat() : location.lat;
                        const lng = typeof location.lng === 'function' ? location.lng() : location.lng;

                        latitudeElement.value = Number(lat).toFixed(7);
                        longitudeElement.value = Number(lng).toFixed(7);
                        marker.setPosition({ lat: Number(lat), lng: Number(lng) });
                        map.setCenter({ lat: Number(lat), lng: Number(lng) });
                        map.setZoom(zoom);
                    };

                    map.addListener('click', (event) => setLocation(event.latLng));
                    marker.addListener('dragend', (event) => setLocation(event.latLng));

                    const autocomplete = new PlaceAutocompleteElement({
                        includedRegionCodes: ['ng'],
                    });
                    autocomplete.placeholder = 'Search an address, landmark or business';
                    searchElement.appendChild(autocomplete);

                    autocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
                        const place = placePrediction.toPlace();
                        await place.fetchFields({
                            fields: ['formattedAddress', 'location', 'addressComponents'],
                        });

                        if (!place.location) {
                            return;
                        }

                        setLocation(place.location);

                        if (place.formattedAddress) {
                            document.querySelector('input[name="address"]').value = place.formattedAddress;
                        }

                        const components = Object.fromEntries((place.addressComponents || []).flatMap((component) =>
                            component.types.map((type) => [type, component.longText])
                        ));
                        const city = components.locality || components.postal_town || components.administrative_area_level_2;
                        const state = components.administrative_area_level_1;

                        if (city) document.querySelector('input[name="city"]').value = city;
                        if (state) document.querySelector('input[name="state"]').value = state;
                    });
                };
            </script>
            <script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode(config('services.google.maps_browser_key')) }}&libraries=places&v=weekly&callback=initLocationPicker" async defer></script>
        @else
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const mapElement = document.getElementById('location-map-osm');
                    const searchInput = document.getElementById('osm-location-search');
                    const resultsEl = document.getElementById('osm-search-results');
                    const latitudeElement = document.getElementById('location-latitude');
                    const longitudeElement = document.getElementById('location-longitude');
                    const addressInput = document.querySelector('input[name="address"]');
                    const cityInput = document.querySelector('input[name="city"]');
                    const stateInput = document.querySelector('input[name="state"]');

                    if (!mapElement || !latitudeElement || !longitudeElement || !window.L) {
                        return;
                    }

                    const fallback = [6.5244, 3.3792];
                    const initialLat = Number(latitudeElement.value) || fallback[0];
                    const initialLng = Number(longitudeElement.value) || fallback[1];
                    const map = L.map('location-map-osm').setView([initialLat, initialLng], latitudeElement.value && longitudeElement.value ? 16 : 11);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    const marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

                    const setLocation = (lat, lng, zoom = 16) => {
                        latitudeElement.value = Number(lat).toFixed(7);
                        longitudeElement.value = Number(lng).toFixed(7);
                        marker.setLatLng([lat, lng]);
                        map.setView([lat, lng], zoom);
                    };

                    map.on('click', function (e) {
                        setLocation(e.latlng.lat, e.latlng.lng);
                    });
                    marker.on('dragend', function () {
                        const pos = marker.getLatLng();
                        setLocation(pos.lat, pos.lng);
                    });

                    if (searchInput) {
                        searchInput.addEventListener('keydown', async function (e) {
                            if (e.key !== 'Enter') {
                                return;
                            }
                            e.preventDefault();
                            const query = searchInput.value.trim();
                            if (!query || !resultsEl) {
                                return;
                            }
                            resultsEl.innerHTML = '<p class="text-[11px] text-ink-3">Searching…</p>';
                            try {
                                const url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&countrycodes=ng&addressdetails=1&limit=5';
                                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                                const data = await res.json();
                                resultsEl.innerHTML = '';
                                if (!Array.isArray(data) || data.length === 0) {
                                    resultsEl.innerHTML = '<p class="text-[11px] text-ink-3">No results found.</p>';
                                    return;
                                }
                                data.forEach(function (item) {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'w-full text-left rounded-lg border border-rule px-2 py-1.5 text-[11px] hover:bg-tint';
                                    btn.textContent = item.display_name;
                                    btn.addEventListener('click', function () {
                                        const lat = parseFloat(item.lat);
                                        const lng = parseFloat(item.lon);
                                        setLocation(lat, lng, 16);
                                        if (addressInput && item.display_name) {
                                            addressInput.value = item.display_name;
                                        }
                                        if (cityInput && item.address) {
                                            cityInput.value = item.address.city || item.address.town || item.address.village || item.address.county || '';
                                        }
                                        if (stateInput && item.address) {
                                            stateInput.value = item.address.state || '';
                                        }
                                        resultsEl.innerHTML = '';
                                    });
                                    resultsEl.appendChild(btn);
                                });
                            } catch (err) {
                                resultsEl.innerHTML = '<p class="text-[11px] text-danger-text">Search failed.</p>';
                            }
                        });
                    }
                });
            </script>
        @endif
    @endsection
@endif
