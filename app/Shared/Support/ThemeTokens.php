<?php

namespace App\Shared\Support;

use App\Domains\Seller\Models\StorePage;

/**
 * The theme token engine.
 *
 * A seller's choices (three colours, a surface mode, a radius, a typeface)
 * become the full token set a page renders from. Everything derived here is
 * derived from measured light, never from a brightness heuristic: the inks are
 * picked by WCAG contrast ratio against the surface they will sit on, and the
 * washes are built in OKLCH from the seller's own hue at fixed lightness
 * anchors. That is what keeps a brand yellow readable as text and a brand
 * black from turning every panel into a grey slab.
 *
 * The same engine renders the storefront and answers the customiser's live
 * preview, so the preview can never drift from the saved page.
 */
final class ThemeTokens
{
    private const DEFAULT_PRIMARY = '#16a34a';

    /**
     * The tokens for a stored page: brand column plus the JSON settings.
     *
     * @return array{vars: array<string, string>, contrast: array<string, float>, font_link: ?string}
     */
    public static function forPage(StorePage $page): array
    {
        $settings = $page->settings ?? [];

        return self::build(
            primary: (string) $page->brand_color,
            secondary: $settings['secondary_color'] ?? null,
            accent: $settings['accent_color'] ?? null,
            mode: $settings['surface'] ?? null,
            radius: $settings['radius'] ?? null,
            font: $settings['font'] ?? null,
            themeFont: config('themes.'.$page->theme->value.'.font', []),
            themeFontLink: config('themes.'.$page->theme->value.'.fonts_link'),
        );
    }

    /**
     * The tokens for a set of unsaved choices.
     *
     * Used by the customiser's live preview, which must show the page the
     * seller is about to save rather than the page they are looking at.
     *
     * @return array{vars: array<string, string>, contrast: array<string, float>, font_link: ?string}
     */
    public static function fromChoices(array $choices): array
    {
        return self::build(
            primary: $choices['primary'] ?? self::DEFAULT_PRIMARY,
            secondary: $choices['secondary'] ?? null,
            accent: $choices['accent'] ?? null,
            mode: $choices['surface'] ?? null,
            radius: $choices['radius'] ?? null,
            font: $choices['font'] ?? null,
            themeFont: $choices['theme_font'] ?? [],
            themeFontLink: $choices['theme_font_link'] ?? null,
        );
    }

    /**
     * The surface a page renders on before anybody switches it.
     *
     * The toggle needs a starting point to compare against: without an
     * attribute on <html>, "am I dark?" has to be answered by the seller's
     * setting rather than guessed at from the OS.
     */
    public static function defaultSurface(): string
    {
        return str_contains((string) config('theming.defaults.surface'), 'dark') ? 'dark' : 'light';
    }

    /**
     * The token set as a :root block, ready to drop into a <style> tag.
     *
     * @param  array<string, string>  $vars
     */
    public static function css(array $vars): string
    {
        $lines = array_map(
            static fn (string $name, string $value): string => "  {$name}: {$value};",
            array_keys($vars),
            $vars,
        );

        return ":root {\n".implode("\n", $lines)."\n}";
    }

    /**
     * Both surfaces of a page, as one stylesheet a visitor can switch between.
     *
     * The inline :root block a seller gets is the same specificity as the one in
     * storefront-theme.css, so it wins on document order and the stylesheet's own
     * dark rules can never fire on a themed page. A switcher therefore cannot be
     * built out of the stylesheet alone: both token sets have to be emitted here,
     * gated on the attribute that the switcher writes.
     *
     * Three states, not two:
     *
     *   no attribute   the seller's own choice, so a page that has never been
     *                  switched opens the way its owner intended
     *   [data-theme]   the visitor's override, which beats the seller because it
     *                  is the later decision and the more local one
     *
     * @return array{css: string, font_link: ?string}
     */
    public static function switchableCss(StorePage $page): array
    {
        $settings = $page->settings ?? [];

        $arguments = [
            'primary' => (string) $page->brand_color,
            'secondary' => $settings['secondary_color'] ?? null,
            'accent' => $settings['accent_color'] ?? null,
            'radius' => $settings['radius'] ?? null,
            'font' => $settings['font'] ?? null,
            'themeFont' => config('themes.'.$page->theme->value.'.font', []),
            'themeFontLink' => config('themes.'.$page->theme->value.'.fonts_link'),
        ];

        // The seller's choice is what renders with no attribute set, so it is
        // the plain :root; the other surface is opt-in from here on.
        $chosen = is_string($settings['surface'] ?? null) && $settings['surface'] !== ''
            ? (string) $settings['surface']
            : (string) config('theming.defaults.surface');

        $preferred = str_contains($chosen, 'dark') ? 'dark' : 'light';
        $alternate = $preferred === 'dark' ? 'light' : 'dark';

        $first = self::build(...$arguments, mode: $preferred);
        $second = self::build(...$arguments, mode: $alternate);

        return [
            'css' => self::css($first['vars'])."\n"
                .'  :root[data-theme="'.$alternate.'"] {'."\n"
                .self::body($second['vars'])."\n"
                .'}',
            'font_link' => $first['font_link'],
        ];
    }

    /**
     * The declarations of a token block, without its selector.
     *
     * @param  array<string, string>  $vars
     */
    private static function body(array $vars): string
    {
        return implode("\n", array_map(
            static fn (string $name, string $value): string => "  {$name}: {$value};",
            array_keys($vars),
            $vars,
        ));
    }

    /**
     * Choices in, tokens out. The single derivation path.
     *
     * @return array{vars: array<string, string>, contrast: array<string, float>, font_link: ?string}
     */
    private static function build(
        string $primary,
        ?string $secondary,
        ?string $accent,
        ?string $mode,
        ?string $radius,
        ?string $font,
        array $themeFont,
        ?string $themeFontLink,
    ): array {
        $primary = self::hex($primary) ?? self::DEFAULT_PRIMARY;
        $secondary = self::hex($secondary) ?? $primary;
        $accent = self::hex($accent) ?? '#f97316';

        $modes = config('theming.modes');
        $mode = is_string($mode) && isset($modes[$mode]) ? $mode : config('theming.defaults.surface');
        $surface = $modes[$mode];

        // Whether the chosen mode is a dark one. Read from the key rather than
        // hardcoded, so adding a third mode to config/theming.php gets the
        // right washes without touching this file.
        $dark = str_contains($mode, 'dark');

        $radii = config('theming.radii');
        $radius = is_string($radius) && isset($radii[$radius]) ? $radius : config('theming.defaults.radius');
        $base = (int) $radii[$radius];

        $fonts = config('theming.fonts');
        $fontLink = $themeFontLink;

        if (is_string($font) && isset($fonts[$font])) {
            $heading = $fonts[$font]['heading'];
            $body = $fonts[$font]['body'];
            $fontLink = $fonts[$font]['link'] ?? $themeFontLink;
        } else {
            // No choice made: the theme's own typeface, so a store opens in
            // Archivo and a service business in Newsreader.
            $heading = $themeFont['heading'] ?? $fonts['system']['heading'];
            $body = $themeFont['body'] ?? $fonts['system']['body'];
        }

        $minimum = (float) config('theming.contrast.text_minimum', 4.5);
        $primaryChannels = self::channels($primary);

        $vars = [
            // Brand channels. Space-separated RGB is the only form that lets
            // Tailwind compose opacity (bg-brand-primary/10) while the raw
            // value stays a single writable custom property.
            '--brand-primary' => $primaryChannels,
            '--brand-secondary' => self::channels($secondary),
            '--brand-accent' => self::channels($accent),

            // Hex aliases. The legacy inline styles in the theme components
            // paint solid fills from these.
            '--brand' => $primary,
            '--brand-ink' => Color::contrast($primary),
            '--brand-text' => Color::textSafe($primary),

            /*
                Washes, edge and veil.

                The three stops a template reaches for when it needs the brand
                colour without shouting: a faint ground, a stronger ground, and
                the hairline that separates one brand-tinted panel from the next.
                Expressed as color-mix against the paper of the mode in force, so
                the seller's surface choice decides the base and one expression
                covers both light and dark without a second rule.
            */
            '--brand-wash' => $dark ? 'color-mix(in srgb, '.$primary.' 14%, '.$surface['paper'].')' : 'color-mix(in srgb, '.$primary.' 7%, '.$surface['paper'].')',
            '--brand-wash-2' => $dark ? 'color-mix(in srgb, '.$primary.' 22%, '.$surface['paper'].')' : 'color-mix(in srgb, '.$primary.' 14%, '.$surface['paper'].')',
            '--brand-edge' => $dark
                ? 'color-mix(in srgb, '.$primary.' 45%, '.$surface['rule'].')'
                : 'color-mix(in srgb, '.$primary.' 32%, '.$surface['rule'].')',
            // The translucent ground the sticky header takes once the page
            // scrolls. Not a wash: it has to sit over a photograph.
            '--brand-veil' => $surface['card_glass'],

            // Channel aliases for the two inks, because a theme key feeding
            // rgb(var(--x) / <alpha-value>) cannot read a hex.
            '--brand-ink-rgb' => self::channels(Color::contrast($primary)),
            '--brand-text-rgb' => self::channels(Color::textSafe($primary)),

            '--brand-l' => self::lightness($primary),
            '--brand-c' => self::chroma($primary),
            '--brand-h' => self::hue($primary),

            '--secondary-ink' => Color::contrast($secondary),
            '--secondary-text' => Color::textSafe($secondary),
            '--secondary-l' => self::lightness($secondary),
            '--secondary-c' => self::chroma($secondary),
            '--secondary-h' => self::hue($secondary),

            '--accent' => $accent,
            '--accent-ink' => Color::contrast($accent),
            '--accent-text' => Color::textSafe($accent),
            '--accent-l' => self::lightness($accent),
            '--accent-c' => self::chroma($accent),
            '--accent-h' => self::hue($accent),

            // Surfaces. `paper` is the page, `surface` the band behind cards,
            // `card` the card itself, `card_glass` the translucent variant for
            // panels that float over content.
            '--paper' => $surface['paper'],
            '--bg-surface' => $surface['surface'],
            '--bg-card' => $surface['card'],
            '--bg-card-glass' => $surface['card_glass'],

            /*
                The same three surfaces under the names the design system uses.

                A template should not have to know which of two names means the
                page, and the components (.card, .shell, .t-title and the rest)
                all reach for the short form. Emitting both keeps one derivation
                behind two vocabularies rather than two derivations.
            */
            '--surface' => $surface['surface'],
            '--card' => $surface['card'],
            '--card-glass' => $surface['card_glass'],

            // Hairlines. `rule` separates; `border-subtle` only suggests an
            // edge, and keeps its channels so utilities can fade it further.
            '--rule' => $surface['rule'],
            '--rule-rgb' => self::channels($surface['rule']),
            '--border-subtle-rgb' => $surface['subtle'],
            '--border-subtle' => 'rgb('.$surface['subtle'].' / '.$surface['subtle_alpha'].')',

            // Ink, measured against the paper it will sit on.
            '--ink' => self::readable($surface['ink'], $surface['paper'], $minimum),
            '--ink-2' => self::readable($surface['ink_2'], $surface['paper'], $minimum),
            '--ink-3' => self::readable($surface['ink_3'], $surface['paper'], $minimum),

            // Form controls. Emitted so the base input styles follow the
            // surface: a dark page with a white search box is the failure
            // mode this prevents. They fall back to the platform's own
            // greys on any page the engine has not themed.
            '--input-border' => $surface['rule'],
            '--input-border-hover' => $surface['input_border_hover'],
            '--input-bg' => $surface['card'],
            '--input-ring' => 'rgb('.$primaryChannels.' / 0.35)',
            '--input-ring-border' => $primary,
            '--input-radius' => (int) round($base * 0.6).'px',

            // The OKLCH lightness anchors the washes and edges are built from,
            // per surface mode. See storefront-theme.css for the calc.
            '--wash-l' => $surface['wash_l'],
            '--wash-2-l' => $surface['wash_2_l'],
            '--edge-l' => $surface['edge_l'],

            // Elevation and ambient light. The glows are radial gradients
            // rather than flat washes so a section can light itself from one
            // corner in the brand's hue without any extra element.
            '--shadow-rest' => $surface['rest'],
            '--shadow-lift' => $surface['lift'],
            '--glow-primary' => self::glow($primary, $surface['glow_opacity']),
            '--glow-accent' => self::glow($accent, $surface['glow_opacity']),
            // The short form, for .card:hover and the floating notification
            // cards, which want a halo rather than a gradient.
            '--glow' => $dark
                ? '0 0 32px -4px color-mix(in srgb, '.$primary.' 36%, transparent)'
                : '0 0 24px -4px color-mix(in srgb, '.$primary.' 22%, transparent)',

            // The focus ring. Derived, never a literal blue: an outline that
            // does not belong to the brand is an outline nobody chose.
            '--focus-ring' => $dark
                ? 'color-mix(in srgb, '.$primary.' 65%, #ffffff)'
                : 'color-mix(in srgb, '.$primary.' 85%, #000000)',

            // The page gutter. Fluid rather than fixed, so a phone gets 1rem and
            // a desktop gets 2rem from one declaration.
            '--gutter' => 'clamp(1rem, 0.6rem + 1.6vw, 2rem)',

            // The radius dial and its derived stops, under both vocabularies.
            // One number moves the whole page; every stop below is a fraction
            // of it, so buttons, cards and plates stay in proportion at every
            // position on the dial including zero.
            '--radius-brand' => $base.'px',
            '--radius-brand-sm' => (int) round($base * 0.6).'px',
            '--radius-brand-lg' => (int) round($base * 1.35).'px',
            '--radius' => $base.'px',
            '--radius-sm' => (int) round($base * 0.6).'px',
            '--radius-lg' => (int) round($base * 1.35).'px',
            '--radius-pill' => '999px',

            // Typography, under both vocabularies.
            '--font-brand' => $body,
            '--font-brand-display' => $heading,
            '--font-heading-stack' => $heading,
            '--font-body-stack' => $body,
            '--font-heading' => $heading,
            '--font-body' => $body,
        ];

        return [
            'vars' => $vars,
            'contrast' => self::contrastReport($primary, $accent, $surface, $vars),
            'font_link' => $fontLink,
        ];
    }

    /**
     * The ratios the customiser shows as AA badges.
     *
     * @param  array<string, mixed>  $surface
     * @param  array<string, string>  $vars
     * @return array<string, float>
     */
    private static function contrastReport(string $primary, string $accent, array $surface, array $vars): array
    {
        return [
            'ink_on_primary' => Color::contrastRatio($primary, $vars['--brand-ink']),
            'primary_on_paper' => Color::contrastRatio($vars['--brand-text'], $surface['paper']),
            'accent_on_paper' => Color::contrastRatio($vars['--accent-text'], $surface['paper']),
            'body_on_paper' => Color::contrastRatio($vars['--ink'], $surface['paper']),
            'muted_on_paper' => Color::contrastRatio($vars['--ink-2'], $surface['paper']),
            'accent_chroma_hint' => self::chromaFloat($accent),
        ];
    }

    /**
     * The first candidate ink that clears the minimum on this paper.
     *
     * Measured, not assumed: if a mode's neutrals are ever retuned and one
     * of them drops below AA, the next candidate takes over instead of the
     * page quietly shipping unreadable grey.
     *
     * @param  list<string>  $candidates
     */
    private static function readable(array $candidates, string $paper, float $minimum): string
    {
        $best = $candidates[0];
        $bestRatio = 0.0;

        foreach ($candidates as $candidate) {
            $ratio = Color::contrastRatio($candidate, $paper);

            if ($ratio >= $minimum) {
                return $candidate;
            }

            if ($ratio > $bestRatio) {
                $best = $candidate;
                $bestRatio = $ratio;
            }
        }

        return $best;
    }

    /**
     * An ambient radial wash in a colour, for hero and section lighting.
     */
    private static function glow(string $hex, string $opacity): string
    {
        return 'radial-gradient(60rem 30rem at 50% -6%, rgb('.self::channels($hex).' / '.$opacity.'), transparent 70%)';
    }

    /**
     * A hex colour as space-separated RGB channels, or null if not a hex.
     */
    private static function channels(string $hex): string
    {
        $hex = ltrim(strtolower($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '0 0 0';
        }

        return implode(' ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }

    /**
     * A six-digit hex, normalised to lower case, or null if it is not one.
     *
     * Nullable on purpose: a page that has never chosen a secondary or an
     * accent arrives with null settings, and the engine falls back rather
     * than failing the render.
     */
    private static function hex(?string $hex): ?string
    {
        return $hex !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1 ? strtolower($hex) : null;
    }

    private static function lightness(string $hex): string
    {
        return number_format(Color::oklch($hex)[0], 4, '.', '');
    }

    private static function chroma(string $hex): string
    {
        return number_format(Color::oklch($hex)[1], 4, '.', '');
    }

    private static function hue(string $hex): string
    {
        return number_format(Color::oklch($hex)[2], 1, '.', '');
    }

    private static function chromaFloat(string $hex): float
    {
        return round(Color::oklch($hex)[1], 3);
    }
}
