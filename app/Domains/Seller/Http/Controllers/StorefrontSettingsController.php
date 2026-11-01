<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Billing\Services\Feature;
use App\Domains\Seller\Enums\PriceTier;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\BlockRenderer;
use App\Domains\Seller\Models\PageBlock;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Rules\NigerianPhone;
use App\Shared\Support\Html;
use App\Shared\Support\Money;
use App\Shared\Support\PhoneNumber;
use App\Shared\Support\SocialLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The seller's storefront settings.
 *
 * Replaces the old page builder. There is no enable/disable checkbox and no drag
 * handle any more: the theme decides the order, and a block with nothing in it
 * does not appear. What is here is the content, and only the fields each block
 * actually uses — which is the fix for a builder that offered a body textarea
 * under Categories, where no body was ever rendered.
 */
class StorefrontSettingsController extends Controller
{
    /**
     * The tabs, and the two that are not theme pages.
     *
     * brand, details and listing are page settings rather than page content —
     * they survive whichever tab the seller is editing and do not come from the
     * block registry, which is why they are listed here rather than in
     * config/themes.php.
     */
    private const PAGES = ['brand', 'details', 'delivery', 'listing', 'home', 'about', 'contact'];

    public function edit(Request $request, BlockRenderer $blocks): View
    {
        $page = $request->user()->storePages()->firstOrFail();
        $tab = in_array($request->query('tab'), self::PAGES, true) ? $request->query('tab') : 'home';

        return view('dashboard.storefront.index', [
            'page' => $page,
            'tab' => $tab,
            'tabs' => $this->tabs($page),
            // Brand, details and the directory listing are not blocks: they are
            // page settings, which is why they survive whichever tab the seller
            // is editing.
            'blocks' => in_array($tab, ['brand', 'details', 'delivery', 'listing'], true)
                ? collect()
                : $blocks->editableForPage($page, $tab),
            'socialPlatforms' => SocialLinks::platforms(),
            'priceTiers' => PriceTier::ordered(),
            'discoveryOn' => Feature::enabled('discovery'),
            'deliveryProfile' => $page->deliveryProfile()->with('zones')->first(),

            // The three presets, resolved rather than raw, so the form shows what
            // is actually in force — a seller who never opened the tab should
            // see the same defaults ThemeTokens derives from.
            'brandSettings' => [
                'surface' => $page->settings['surface'] ?? config('theming.defaults.surface'),
                'radius' => $page->settings['radius'] ?? config('theming.defaults.radius'),
                'font' => $page->settings['font'] ?? null,
                'theme_preset' => $page->settings['theme_preset'] ?? ($page->theme === Theme::Service ? 'minimal-swiss' : 'catalog'),
            ],
            'brandFontNote' => 'Typeface locked to the selected theme direction.',
            'carouselSlides' => $page->carouselSlides()->get(),
            'carouselEnabled' => PlatformSetting::value("theme.{$page->theme->value}.carousel", true),
            'heroStylesEnabled' => PlatformSetting::value("theme.{$page->theme->value}.hero_styles", true),
            'layoutOptions' => collect(config("themes.{$page->theme->value}.pages.home.blocks", []))
                ->reject(fn (string $key): bool => in_array($key, config("themes.{$page->theme->value}.required", []), true))
                ->mapWithKeys(fn (string $key): array => [
                    $key => config("themes.{$page->theme->value}.blocks.home_{$key}.label", ucfirst($key)),
                ]),
        ]);
    }

    /**
     * Save one page's blocks.
     *
     * Only keys the theme declares for that page are accepted, so a crafted
     * request cannot invent a block or write to another page's content.
     */
    public function update(Request $request, string $tab): RedirectResponse
    {
        abort_unless(in_array($tab, self::PAGES, true), 404);

        $page = $request->user()->storePages()->firstOrFail();

        if ($tab === 'brand') {
            return $this->saveBrand($request, $page);
        }

        if ($tab === 'details') {
            return $this->saveDetails($request, $page);
        }

        if ($tab === 'delivery') {
            return $this->saveDelivery($request, $page);
        }

        if ($tab === 'listing') {
            return $this->saveListing($request, $page);
        }

        $declared = config("themes.{$page->theme->value}.pages.{$tab}.blocks", []);

        abort_if($declared === [], 404);

        $validated = $request->validate($this->rules($page, $tab, $declared));

        $submitted = $validated['blocks'] ?? [];

        foreach ($declared as $key) {
            if (! array_key_exists($key, $submitted)) {
                continue;
            }

            $row = $submitted[$key];
            $block = $page->blocks()->where('page', $tab)->where('key', $key)->first()
                ?? new PageBlock(['store_page_id' => $page->getKey(), 'page' => $tab, 'key' => $key]);

            $content = $this->cleanContent($page, $tab, $key, $row, $block->content ?? []);

            if ($content === []) {
                // Nothing left to say. Removing the row is how a block disappears
                // now that there is no switch to turn off.
                $block->delete();

                continue;
            }

            $block->content = $content;
            $block->save();
        }

        return back()->with('status', "Your {$page->theme->siteNoun()} has been updated.");
    }

    /**
     * How customers reach the business, and where else to find it.
     *
     * Separate from the page content because none of it belongs to a single page:
     * the address shows on the contact page and in the directory, the social
     * links in the footer and in the structured data. WhatsApp and the city were
     * collected once at onboarding and this is the first chance to change them.
     */
    private function saveDetails(Request $request, StorePage $page): RedirectResponse
    {
        $validated = $request->validate([
            'tagline' => ['nullable', 'string', 'max:160'],
            'public_email' => ['nullable', 'email', 'max:190'],
            'whatsapp_number' => ['required', new NigerianPhone],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'social' => ['array'],
            'social.*' => ['nullable', 'string', 'max:190'],
        ]);

        $page->fill([
            'tagline' => $validated['tagline'] ?? null,
            'public_email' => $validated['public_email'] ?? null,
            'whatsapp_number' => PhoneNumber::normalize($validated['whatsapp_number']),
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'],
            'state' => $validated['state'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            // Normalised rather than stored raw, so what renders is always a URL
            // we built ourselves and never something a seller typed.
            'social_links' => SocialLinks::normalise($validated['social'] ?? []),
        ])->save();

        return back()->with('status', 'Your business details have been updated.');
    }

    /**
     * Store-wide delivery defaults.
     *
     * Zones are replaced wholesale rather than diffed: the form always submits
     * the complete list, so deleting and recreating is the only way to guarantee
     * the saved areas match what the seller just saw. Fees arrive in naira and
     * are stored in kobo like every other price.
     */
    private function saveDelivery(Request $request, StorePage $page): RedirectResponse
    {
        $validated = $request->validate([
            'offers_delivery' => ['sometimes', 'boolean'],
            'offers_pickup' => ['sometimes', 'boolean'],
            'offers_home_service' => ['sometimes', 'boolean'],
            'free_over' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'radius_km' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:255'],
            'zones' => ['nullable', 'array', 'max:30'],
            'zones.*.name' => ['nullable', 'string', 'max:80'],
            'zones.*.state' => ['nullable', 'string', 'max:80'],
            'zones.*.fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $profile = $page->deliveryProfile()->firstOrNew([]);
        $profile->fill([
            'offers_delivery' => $request->boolean('offers_delivery'),
            'offers_pickup' => $request->boolean('offers_pickup'),
            'offers_home_service' => $request->boolean('offers_home_service'),
            'free_over_kobo' => filled($validated['free_over'] ?? null)
                ? Money::toKobo($validated['free_over'])
                : null,
            'radius_km' => $validated['radius_km'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        $zones = collect($validated['zones'] ?? [])
            ->map(fn (array $zone): array => [
                'name' => trim((string) ($zone['name'] ?? '')),
                'state' => filled($zone['state'] ?? null) ? trim((string) $zone['state']) : null,
                'fee_kobo' => filled($zone['fee'] ?? null) ? Money::toKobo($zone['fee']) : 0,
            ])
            ->filter(fn (array $zone): bool => $zone['name'] !== '')
            ->values()
            ->all();

        $profile->zones()->delete();
        $profile->zones()->createMany($zones);

        return back()->with('status', 'Your delivery settings have been updated.');
    }

    /**
     * The one design setting a theme has: colour, logo, cover.
     *
     * These are not blocks. They sit on the page rather than on a page of it,
     * so they survive whichever tab the seller is editing and show up in the
     * directory listing and the JSON-LD.
     */
    private function saveBrand(Request $request, StorePage $page): RedirectResponse
    {
        $validated = $request->validate([
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // The three presets. Each is a key into config/theming.php rather
            // than a free value, so a crafted request cannot invent a colour no
            // contrast check was ever run against.
            'surface' => ['sometimes', 'nullable', Rule::in(array_keys(config('theming.modes')))],
            'radius' => ['sometimes', 'nullable', Rule::in(array_keys(config('theming.radii')))],
            'font' => ['sometimes', 'nullable', Rule::in(array_merge([''], array_keys(config('theming.fonts'))))],
            'theme_preset' => ['sometimes', Rule::in($this->availablePresets($page->theme))],
            'hero_style' => ['sometimes', Rule::in(['immersive', 'split', 'minimal'])],
            'hero_alignment' => ['sometimes', Rule::in(['left', 'center'])],
            'hero_overlay' => ['sometimes', Rule::in(['soft', 'deep', 'none'])],
            'carousel_autoplay' => ['sometimes', 'boolean'],
            'show_trust_strip' => ['sometimes', 'boolean'],
            'show_metrics' => ['sometimes', 'boolean'],
            'hidden_sections' => ['sometimes', 'array'],
            'hidden_sections.*' => ['string', Rule::in($this->layoutKeys($page))],
            'logo' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
            'cover' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
            'og_image' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_cover' => ['sometimes', 'boolean'],
            'remove_og_image' => ['sometimes', 'boolean'],
        ]);

        $page->brand_color = $validated['brand_color'];

        // Presets live in settings rather than columns because they are a menu,
        // not a measurement: adding one is a config change, not a migration.
        // Absent keys fall back to the theme defaults rather than to null, so a
        // form that posts only the colour cannot silently reset the rest.
        $settings = $page->settings ?? [];
        $settings['surface'] = $validated['surface'] ?? ($settings['surface'] ?? config('theming.defaults.surface'));
        $settings['radius'] = $validated['radius'] ?? ($settings['radius'] ?? config('theming.defaults.radius'));

        $settings['theme_preset'] = $validated['theme_preset'] ?? ($settings['theme_preset'] ?? ($page->theme === Theme::Service ? 'minimal-swiss' : 'catalog'));
        $settings['font'] = null;
        $settings['hero_style'] = $validated['hero_style'] ?? ($settings['hero_style'] ?? 'immersive');
        $settings['hero_alignment'] = $validated['hero_alignment'] ?? ($settings['hero_alignment'] ?? 'center');
        $settings['hero_overlay'] = $validated['hero_overlay'] ?? ($settings['hero_overlay'] ?? 'deep');
        $settings['carousel_autoplay'] = $request->boolean('carousel_autoplay');
        $settings['show_trust_strip'] = $request->boolean('show_trust_strip');
        $settings['show_metrics'] = $request->boolean('show_metrics');
        $hiddenSections = $settings['hidden_sections'] ?? [];
        $hiddenSections['home'] = array_values($validated['hidden_sections'] ?? []);
        $settings['hidden_sections'] = $hiddenSections;

        $page->settings = $settings;

        foreach (['logo' => 'logo_path', 'cover' => 'cover_path', 'og_image' => 'og_image_path'] as $input => $column) {
            if ($request->boolean("remove_{$input}")) {
                if ($page->{$column}) {
                    Storage::disk('public')->delete($page->{$column});
                }

                $page->{$column} = null;

                continue;
            }

            if ($request->hasFile($input)) {
                if ($page->{$column}) {
                    Storage::disk('public')->delete($page->{$column});
                }

                $page->{$column} = $request->file($input)->store("storefront/{$page->getKey()}/brand", 'public');
            }
        }

        $page->save();

        return back()->with('status', 'Your brand has been updated.');
    }

    /** @return list<string> */
    private function layoutKeys(StorePage $page): array
    {
        return collect(config("themes.{$page->theme->value}.pages.home.blocks", []))
            ->reject(fn (string $key): bool => in_array($key, config("themes.{$page->theme->value}.required", []), true))
            ->values()
            ->all();
    }

    /**
     * Whether this business appears in the public discovery hub.
     *
     * Opting in is a choice, not a consequence of publishing, so it gets its own
     * tab and its own explicit control. Without it the hub would be a list of
     * sellers who never heard of it, and there would be no way back for one who
     * did not want to be there.
     *
     * Two things are deliberately not editable here: rating and review_count are
     * aggregates of what buyers write, and is_featured is a placement the platform
     * sells. A seller able to type their own rating would make the rating on the
     * card worth nothing to the next person who reads it.
     */
    private function saveListing(Request $request, StorePage $page): RedirectResponse
    {
        $validated = $request->validate([
            'is_directory_opted_in' => ['nullable', 'boolean'],
            'price_tier' => ['nullable', 'integer', Rule::in(array_column(PriceTier::cases(), 'value'))],
            'reduce_motion' => ['nullable', 'boolean'],
            'animated_background' => ['nullable', 'boolean'],
        ]);

        $optedIn = $request->boolean('is_directory_opted_in');

        $page->fill([
            'is_directory_opted_in' => $optedIn,
            'price_tier' => PriceTier::tryFromRequest($validated['price_tier'] ?? null),
            'settings' => array_merge($page->settings ?? [], [
                'reduce_motion' => $request->boolean('reduce_motion'),
                'animated_background' => $request->boolean('animated_background'),
            ]),
        ])->save();

        // Turning the listing off has to survive a seller turning it back on.
        // Leaving the rating behind would let a business reappear carrying a
        // reputation it earned before it asked to be listed again.
        return back()->with('status', $optedIn
            ? 'You are listed in the mypage.ng directory.'
            : 'You have been removed from the mypage.ng directory.');
    }

    /**
     * Validation rules built from the block schemas.
     *
     * Derived rather than hand-written so a block cannot declare a field the
     * controller never checks, and so each field keeps a length limit matching
     * what it actually is: a button label is not a paragraph.
     *
     * @param  list<string>  $declared
     * @return array<string, list<string>>
     */
    private function rules(StorePage $page, string $tab, array $declared): array
    {
        $rules = [
            'blocks' => ['array'],
            'blocks.*' => ['array'],
        ];

        foreach ($declared as $key) {
            $fields = $this->schema($page, $tab, $key)['fields'] ?? [];

            foreach ($fields as $name => $field) {
                $path = "blocks.{$key}.{$name}";

                $rules[$path] = match ($field['type'] ?? 'text') {
                    'richtext' => ['sometimes', 'nullable', 'string', 'max:20000'],
                    'textarea' => ['sometimes', 'nullable', 'string', 'max:5000'],
                    'image' => ['sometimes', 'nullable', 'file', 'image', 'max:4096'],
                    'repeater' => ['sometimes', 'nullable', 'array'],
                    default => ['sometimes', 'nullable', 'string', 'max:500'],
                };

                if (($field['type'] ?? '') === 'repeater') {
                    foreach ($field['fields'] ?? [] as $subName => $subField) {
                        // Deliberately not `required`. The add button gives a
                        // seller an empty row to type into, and refusing the
                        // whole form because they left one blank is worse than
                        // quietly dropping it on save.
                        $rules["{$path}.*.{$subName}"] = ['sometimes', 'nullable', 'string', 'max:500'];
                    }
                }
            }
        }

        return $rules;
    }

    /** @return list<string> */
    private function availablePresets(Theme $theme): array
    {
        return collect(config('themes.presets'))
            ->filter(fn (array $preset): bool => in_array($theme->value, $preset['business_types'], true))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(StorePage $page, string $tab, string $key): array
    {
        return config("themes.{$page->theme->value}.blocks.".str_replace('.', '_', $tab.'.'.$key), []);
    }

    /**
     * Keep only the fields this block declares, sanitised.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function cleanContent(StorePage $page, string $tab, string $key, array $row, array $existing): array
    {
        $fields = $this->schema($page, $tab, $key)['fields'] ?? [];

        $content = [];

        foreach ($fields as $name => $field) {
            $type = $field['type'] ?? 'text';

            // Every field carries its own name. They used to share one
            // `string` input, which meant a hero's headline and button text
            // overwrote each other and only the last one submitted survived.
            $value = match ($type) {
                'richtext' => Html::sanitize((string) ($row[$name] ?? '')),
                'repeater' => $this->cleanRepeater($row[$name] ?? [], $field['fields'] ?? []),
                'image' => $row[$name] ?? null,
                default => isset($row[$name]) ? trim((string) $row[$name]) : null,
            };

            if ($type === 'image') {
                // An existing image survives unless a new one replaces it.
                if ($value === null || $value === '') {
                    if (! empty($existing[$name])) {
                        $content[$name] = $existing[$name];
                    }

                    continue;
                }

                $content[$name] = $value->store("storefront/{$page->getKey()}/{$tab}", 'public');

                continue;
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $content[$name] = $value;
            }
        }

        return $content;
    }

    /**
     * Tidy up a list of rows the seller added.
     *
     * A row with nothing in it is dropped. So is one missing a field the schema
     * marks required: clearing the review but leaving the name behind should not
     * leave a nameless quote's author stranded on the page.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{type: string, label: string, required?: bool}>  $fields
     * @return array<int, array<string, string>>
     */
    private function cleanRepeater(array $rows, array $fields): array
    {
        return collect($rows)
            ->map(fn ($row) => collect($fields)
                ->mapWithKeys(fn ($definition, $name) => [
                    $name => trim((string) ($row[$name] ?? '')),
                ])
                ->reject(fn (string $value) => $value === '')
                ->all())
            ->reject(fn (array $row) => collect($fields)
                ->filter(fn (array $definition) => ! empty($definition['required']))
                ->contains(fn (array $definition, string $name) => blank($row[$name] ?? null)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function tabs(StorePage $page): array
    {
        $available = config("themes.{$page->theme->value}.pages", []);

        $themeTabs = collect(self::PAGES)
            ->filter(fn (string $tab) => ($available[$tab]['blocks'] ?? []) !== [])
            ->mapWithKeys(fn (string $tab) => [$tab => $available[$tab]['label'] ?? ucfirst($tab)])
            ->all();

        // The listing tab is added last and only while the hub exists: offering a
        // seller a switch for a page that 404s is a promise the platform has not
        // kept yet.
        return Feature::enabled('discovery')
            ? $themeTabs + ['listing' => 'Directory']
            : $themeTabs;
    }
}
