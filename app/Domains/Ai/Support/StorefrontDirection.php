<?php

namespace App\Domains\Ai\Support;

use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\PageBlock;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Html;
use Illuminate\Support\Str;

/**
 * The single reviewable storefront proposal: appearance settings plus copy.
 *
 * The model never writes straight to the page. Everything it returns is
 * normalised here against the theme registry first, so an unknown block, a
 * colour that is not a colour, or a made-up review cannot reach the storefront.
 */
final class StorefrontDirection
{
    private const PAGES = ['home', 'about', 'contact'];

    private const TEXT_FIELDS = ['text', 'textarea', 'richtext'];

    /**
     * The compact registry sent to the model.
     *
     * @return array<string, mixed>
     */
    public static function registry(StorePage $page): array
    {
        $theme = $page->theme->value;
        $copy = [];

        foreach (self::PAGES as $pageKey) {
            foreach (config("themes.{$theme}.pages.{$pageKey}.blocks", []) as $blockKey) {
                $fields = collect(self::blockSchema($page, $pageKey, $blockKey)['fields'] ?? [])
                    ->filter(fn (array $field): bool => self::isCopyField($field))
                    ->mapWithKeys(fn (array $field, string $name): array => [$name => self::fieldDefinition($name, $field)])
                    ->all();

                if ($fields !== []) {
                    $copy[$pageKey][$blockKey] = $fields;
                }
            }
        }

        return [
            'text' => self::description($page),
            'business_type' => $page->businessType(),
            'theme_registry' => self::presets($page),
            'surface_registry' => self::keys(config('theming.modes', [])),
            'radius_registry' => self::keys(config('theming.radii', [])),
            'hero_styles' => ['immersive', 'split', 'minimal'],
            'hero_alignments' => ['left', 'center'],
            'hero_overlays' => ['soft', 'deep', 'none'],
            'layout_registry' => self::layoutOptions($page),
            'copy_registry' => $copy,
            'current_settings' => self::currentSettings($page),
            'current_content' => self::currentContent($page),
        ];
    }

    /**
     * Labels and field definitions the review modal needs to render the copy.
     *
     * @return array<string, mixed>
     */
    public static function preview(StorePage $page): array
    {
        $theme = $page->theme->value;
        $pages = [];

        foreach (self::PAGES as $pageKey) {
            $blocks = [];

            foreach (config("themes.{$theme}.pages.{$pageKey}.blocks", []) as $blockKey) {
                $schema = self::blockSchema($page, $pageKey, $blockKey);
                $fields = collect($schema['fields'] ?? [])
                    ->filter(fn (array $field): bool => self::isCopyField($field))
                    ->map(fn (array $field, string $name): array => ['name' => $name, ...self::fieldDefinition($name, $field)])
                    ->values()
                    ->all();

                if ($fields !== []) {
                    $blocks[] = [
                        'key' => $blockKey,
                        'label' => $schema['label'] ?? Str::headline($blockKey),
                        'fields' => $fields,
                    ];
                }
            }

            if ($blocks !== []) {
                $pages[] = [
                    'key' => $pageKey,
                    'label' => config("themes.{$theme}.pages.{$pageKey}.label", Str::headline($pageKey)),
                    'blocks' => $blocks,
                ];
            }
        }

        return [
            'pages' => $pages,
            'hiddenSections' => self::layoutOptions($page),
            'presetLabels' => collect(self::presets($page))->pluck('label', 'key')->all(),
            'surfaceLabels' => collect(self::keys(config('theming.modes', [])))->pluck('label', 'key')->all(),
            'radiusLabels' => collect(self::keys(config('theming.radii', [])))->pluck('label', 'key')->all(),
            'heroStyles' => ['immersive' => 'Immersive image', 'split' => 'Editorial split', 'minimal' => 'Minimal statement'],
            'heroAlignments' => ['left' => 'Left aligned', 'center' => 'Centered'],
            'heroOverlays' => ['soft' => 'Soft contrast', 'deep' => 'Deep contrast', 'none' => 'Clean image'],
        ];
    }

    /**
     * Normalise the model output against the current theme before it is stored.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function sanitize(StorePage $page, array $result): array
    {
        $presetKeys = collect(self::presets($page))->pluck('key')->all();
        $surfaces = array_keys(config('theming.modes', []));
        $radii = array_keys(config('theming.radii', []));
        $layoutKeys = array_keys(self::layoutOptions($page));

        return [
            'theme_key' => in_array($result['theme_key'] ?? null, $presetKeys, true)
                ? $result['theme_key']
                : ($presetKeys[0] ?? 'catalog'),
            'brand_color' => self::hex($result['brand_color'] ?? null, $page->brand_color),
            'accent_color' => self::hex($result['accent_color'] ?? null, $page->settings['accent_color'] ?? $page->brand_color),
            'surface' => in_array($result['surface'] ?? null, $surfaces, true)
                ? $result['surface']
                : config('theming.defaults.surface'),
            'radius' => in_array($result['radius'] ?? null, $radii, true)
                ? $result['radius']
                : config('theming.defaults.radius'),
            'hero_style' => in_array($result['hero_style'] ?? null, ['immersive', 'split', 'minimal'], true)
                ? $result['hero_style']
                : 'immersive',
            'hero_alignment' => in_array($result['hero_alignment'] ?? null, ['left', 'center'], true)
                ? $result['hero_alignment']
                : 'center',
            'hero_overlay' => in_array($result['hero_overlay'] ?? null, ['soft', 'deep', 'none'], true)
                ? $result['hero_overlay']
                : 'deep',
            'carousel_autoplay' => (bool) ($result['carousel_autoplay'] ?? false),
            'show_trust_strip' => (bool) ($result['show_trust_strip'] ?? true),
            'show_metrics' => (bool) ($result['show_metrics'] ?? false),
            'hidden_sections' => array_values(array_intersect((array) ($result['hidden_sections'] ?? []), $layoutKeys)),
            'copy' => self::sanitizeCopy($page, (array) ($result['copy'] ?? [])),
            'reason' => trim((string) ($result['reason'] ?? '')),
        ];
    }

    /**
     * Apply a normalised direction to the page and its blocks.
     *
     * @param  array<string, mixed>  $direction
     */
    public static function apply(StorePage $page, array $direction): void
    {
        $settings = $page->settings ?? [];
        $settings['theme_preset'] = $direction['theme_key'];
        $settings['accent_color'] = $direction['accent_color'];
        $settings['surface'] = $direction['surface'];
        $settings['radius'] = $direction['radius'];
        $settings['hero_style'] = $direction['hero_style'];
        $settings['hero_alignment'] = $direction['hero_alignment'];
        $settings['hero_overlay'] = $direction['hero_overlay'];
        $settings['carousel_autoplay'] = $direction['carousel_autoplay'];
        $settings['show_trust_strip'] = $direction['show_trust_strip'];
        $settings['show_metrics'] = $direction['show_metrics'];

        $hidden = $settings['hidden_sections'] ?? [];
        $hidden['home'] = $direction['hidden_sections'];
        $settings['hidden_sections'] = $hidden;

        $page->forceFill([
            'brand_color' => $direction['brand_color'],
            'settings' => $settings,
        ])->save();

        foreach ($direction['copy'] ?? [] as $pageKey => $blocks) {
            foreach ($blocks as $blockKey => $fields) {
                $block = $page->blocks()->where('page', $pageKey)->where('key', $blockKey)->first()
                    ?? new PageBlock(['store_page_id' => $page->getKey(), 'page' => $pageKey, 'key' => $blockKey]);

                $content = $block->content ?? [];

                foreach ($fields as $name => $value) {
                    $content[$name] = $value;
                }

                if ($content === []) {
                    if ($block->exists) {
                        $block->delete();
                    }

                    continue;
                }

                $block->content = $content;
                $block->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $copy
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function sanitizeCopy(StorePage $page, array $copy): array
    {
        $current = self::currentContent($page);
        $clean = [];

        foreach (self::PAGES as $pageKey) {
            foreach (config("themes.{$page->theme->value}.pages.{$pageKey}.blocks", []) as $blockKey) {
                $submitted = $copy[$pageKey][$blockKey] ?? null;

                if (! is_array($submitted)) {
                    continue;
                }

                // Reviews are the merchant's buyers speaking. A model must never
                // put words in their mouths, so no review content is accepted.
                if ($blockKey === 'reviews') {
                    continue;
                }

                $content = [];

                foreach (self::blockSchema($page, $pageKey, $blockKey)['fields'] ?? [] as $name => $field) {
                    $type = $field['type'] ?? 'text';

                    if ($type === 'image' || ! array_key_exists($name, $submitted)) {
                        continue;
                    }

                    if ($type === 'repeater') {
                        if ($name !== 'items' || $blockKey !== 'faq') {
                            continue;
                        }

                        $rows = self::cleanRows($submitted[$name], $field['fields'] ?? []);

                        if ($rows !== []) {
                            $content[$name] = $rows;
                        }

                        continue;
                    }

                    if (! in_array($type, self::TEXT_FIELDS, true) || ! is_scalar($submitted[$name])) {
                        continue;
                    }

                    $text = trim((string) $submitted[$name]);

                    if ($text === '') {
                        continue;
                    }

                    // Opening hours are a fact. Improve the wording only when
                    // the merchant already supplied the times.
                    if ($name === 'hours' && blank($current[$pageKey][$blockKey][$name] ?? null)) {
                        continue;
                    }

                    $text = mb_substr($text, 0, match ($type) {
                        'richtext' => 20000,
                        'textarea' => 5000,
                        default => 500,
                    });

                    $content[$name] = $type === 'richtext' ? Html::sanitize($text) : $text;
                }

                if ($content !== []) {
                    $clean[$pageKey][$blockKey] = $content;
                }
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<array<string, string>>
     */
    private static function cleanRows(mixed $rows, array $fields): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->map(fn (mixed $row): array => collect($fields)
                ->mapWithKeys(fn (array $definition, string $name): array => [
                    $name => is_array($row) ? trim((string) ($row[$name] ?? '')) : '',
                ])
                ->filter(fn (string $value): bool => $value !== '')
                ->all())
            ->reject(fn (array $row): bool => collect($fields)
                ->filter(fn (array $definition): bool => ! empty($definition['required']))
                ->contains(fn (array $definition, string $name): bool => blank($row[$name] ?? null)))
            ->values()
            ->all();
    }

    /**
     * @return array{theme_preset: string, brand_color: string, accent_color: string, surface: string, radius: string, hero_style: string, hero_alignment: string, hero_overlay: string, carousel_autoplay: bool, show_trust_strip: bool, show_metrics: bool, hidden_sections: list<string>}
     */
    private static function currentSettings(StorePage $page): array
    {
        $settings = $page->settings ?? [];

        return [
            'theme_preset' => $settings['theme_preset'] ?? ($page->theme === Theme::Service ? 'minimal-swiss' : 'catalog'),
            'brand_color' => $page->brand_color,
            'accent_color' => $settings['accent_color'] ?? $page->brand_color,
            'surface' => $settings['surface'] ?? config('theming.defaults.surface'),
            'radius' => $settings['radius'] ?? config('theming.defaults.radius'),
            'hero_style' => $settings['hero_style'] ?? 'immersive',
            'hero_alignment' => $settings['hero_alignment'] ?? 'center',
            'hero_overlay' => $settings['hero_overlay'] ?? 'deep',
            'carousel_autoplay' => (bool) ($settings['carousel_autoplay'] ?? false),
            'show_trust_strip' => (bool) ($settings['show_trust_strip'] ?? true),
            'show_metrics' => (bool) ($settings['show_metrics'] ?? false),
            'hidden_sections' => array_values((array) ($settings['hidden_sections']['home'] ?? [])),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function currentContent(StorePage $page): array
    {
        $content = [];

        foreach ($page->blocks()->whereIn('page', self::PAGES)->get() as $block) {
            $content[$block->page][$block->key] = $block->content ?? [];
        }

        return $content;
    }

    /** @return list<array{key: string, label: string, description: string}> */
    private static function presets(StorePage $page): array
    {
        return collect(config('themes.presets', []))
            ->filter(fn (array $preset): bool => in_array($page->theme->value, $preset['business_types'] ?? [], true))
            ->map(fn (array $preset, string $key): array => [
                'key' => $key,
                'label' => $preset['label'] ?? $key,
                'description' => $preset['description'] ?? '',
            ])
            ->values()
            ->all();
    }

    /** @return list<array{key: string, label: string}> */
    private static function keys(array $values): array
    {
        return collect($values)
            ->keys()
            ->map(fn (string $key): array => ['key' => $key, 'label' => Str::headline($key)])
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public static function layoutOptions(StorePage $page): array
    {
        $theme = $page->theme->value;

        return collect(config("themes.{$theme}.pages.home.blocks", []))
            ->reject(fn (string $key): bool => in_array($key, config("themes.{$theme}.required", []), true))
            ->mapWithKeys(fn (string $key): array => [
                $key => config("themes.{$theme}.blocks.home_{$key}.label", Str::headline($key)),
            ])
            ->all();
    }

    /**
     * A short, factual description of the business for the model.
     */
    public static function description(StorePage $page): string
    {
        $location = trim(implode(', ', array_filter([$page->city, $page->state])));
        $categories = $page->categories()->limit(8)->pluck('name')->implode(', ');
        $products = $page->products()->limit(8)->pluck('name')->implode(', ');
        $services = $page->services()->limit(8)->pluck('name')->implode(', ');

        return trim(implode("\n", array_filter([
            $page->name,
            $page->tagline,
            $page->bio,
            $location !== '' ? "Location: {$location}" : null,
            $categories !== '' ? "Categories: {$categories}" : null,
            $products !== '' ? "Products: {$products}" : null,
            $services !== '' ? "Services: {$services}" : null,
        ])));
    }

    private static function hex(mixed $value, ?string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
            ? strtolower($value)
            : ($fallback ?: '#111827');
    }

    /** @param  array<string, mixed>  $field */
    private static function isCopyField(array $field): bool
    {
        return in_array($field['type'] ?? 'text', self::TEXT_FIELDS, true)
            || ($field['type'] ?? '') === 'repeater';
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function fieldDefinition(string $name, array $field): array
    {
        $definition = [
            'name' => $name,
            'type' => $field['type'] ?? 'text',
            'label' => $field['label'] ?? Str::headline($name),
        ];

        if (($field['type'] ?? '') === 'repeater') {
            $definition['fields'] = collect($field['fields'] ?? [])
                ->map(fn (array $sub, string $subName): array => [
                    'name' => $subName,
                    'type' => $sub['type'] ?? 'text',
                    'label' => $sub['label'] ?? Str::headline($subName),
                ])
                ->values()
                ->all();
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private static function blockSchema(StorePage $page, string $pageKey, string $blockKey): array
    {
        return config("themes.{$page->theme->value}.blocks.".str_replace('.', '_', $pageKey.'.'.$blockKey), []);
    }
}
