<?php

/*
|--------------------------------------------------------------------------
| Theme tokens — the choices behind the tokens
|--------------------------------------------------------------------------
|
| A seller picks a handful of honest choices: three colours, a surface mode,
| one radius dial and one typeface. Everything else on the page — washes,
| hairlines, inks, shadows, glows — is derived from those choices at render
| time by App\Shared\Support\ThemeTokens, so there is exactly one place where
| a colour becomes a design and no template ever hardcodes a surface.
|
| This file is the menu, not the kitchen. Nothing here computes anything; it
| declares what may be chosen and what each choice costs. The engine reads it.
|
*/

return [

    /*
    | What a fresh page gets. `font: null` means "the theme's own typeface",
    | so a store opens in Archivo and a service business in Newsreader until
    | the seller deliberately picks something else.
    */
    'defaults' => [
        'surface' => 'light',
        'radius' => 'rounded',
        'font' => null,
    ],

    /*
    | Typefaces. One family per choice, carrying heading and body together:
    | a second face inside one page is a second product. Each entry ships the
    | <link> the layout needs, so a choice made here is a page that loads what
    | it renders.
    */
    'fonts' => [
        'editorial' => [
            'label' => 'Archivo',
            'note' => 'Tight grotesque, tabular figures — the store default.',
            'heading' => "'Archivo', system-ui, sans-serif",
            'body' => "'Archivo', system-ui, sans-serif",
            'link' => 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap',
        ],
        'business' => [
            'label' => 'Newsreader',
            'note' => 'Text serif, warm at reading sizes — the service default.',
            'heading' => "'Newsreader', Georgia, serif",
            'body' => "'Newsreader', Georgia, serif",
            'link' => 'https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600;6..72,700&display=swap',
        ],
        'grotesk' => [
            'label' => 'Plus Jakarta Sans',
            'note' => 'Geometric, friendly at large sizes.',
            'heading' => "'Plus Jakarta Sans', Inter, sans-serif",
            'body' => "'Plus Jakarta Sans', Inter, sans-serif",
            'link' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
        ],
        'system' => [
            'label' => 'Inter',
            'note' => 'The platform interface face. Nothing to download.',
            'heading' => 'Inter, system-ui, sans-serif',
            'body' => 'Inter, system-ui, sans-serif',
            'link' => null,
        ],
    ],

    /*
    | The radius dial. One number moves the whole page: small radii are
    | derived from it (var(--radius-brand-sm) and friends) so buttons, cards
    | and plates stay in proportion at every stop, including zero.
    */
    'radii' => [
        'sharp' => 0,
        'soft' => 6,
        'rounded' => 12,
        'plush' => 20,
        'showcase' => 28,
    ],

    /*
    | Surface modes. The neutrals behind everything, plus the lightness
    | anchors the OKLCH washes are built from.
    |
    | The inks are candidate lists, not single values: the engine measures
    | each against this mode's paper and takes the first that clears the
    | contrast minimum below, which is what keeps a muted grey legible on a
    | dark surface without anybody auditing hex codes by hand.
    |
    | `subtle` is a channel triplet (for rgb(var(--border-subtle-rgb) / a))
    | rather than a colour, so the same hairline can be faded further with
    | Tailwind's opacity utilities.
    */
    'modes' => [

        'light' => [
            'paper' => '#ffffff',
            'surface' => '#f8fafc',
            'card' => '#ffffff',
            'card_glass' => 'rgb(255 255 255 / 0.72)',
            'rule' => '#e6e8ec',
            'input_border_hover' => '#cbd5e1',
            'subtle' => '15 23 42',
            'subtle_alpha' => '0.06',
            'ink' => ['#15171b', '#0f172a', '#111827'],
            'ink_2' => ['#5b6270', '#4b5563', '#475569'],
            'ink_3' => ['#6b7280', '#64748b', '#526077'],
            'wash_l' => '0.965',
            'wash_2_l' => '0.93',
            'edge_l' => '0.855',
            'shadow' => '15 23 42',
            'rest' => '0 1px 2px rgb(15 23 42 / 0.05), 0 1px 3px rgb(15 23 42 / 0.06)',
            'lift' => '0 16px 40px -12px rgb(15 23 42 / 0.16), 0 2px 6px rgb(15 23 42 / 0.06)',
            'glow_opacity' => '0.15',
        ],

        'dark' => [
            'paper' => '#0b0e14',
            'surface' => '#090c11',
            'card' => '#11151d',
            'card_glass' => 'rgb(17 21 29 / 0.75)',
            'rule' => '#232a36',
            'input_border_hover' => '#2f3844',
            'subtle' => '255 255 255',
            'subtle_alpha' => '0.08',
            'ink' => ['#f4f6f8', '#e8ecf1', '#d7dde5'],
            'ink_2' => ['#a8b0bd', '#98a1ae', '#8b94a2'],
            'ink_3' => ['#8b94a2', '#7d8694', '#6f7887'],
            // Washes on a dark ground are dark tints, not pale ones: a brand
            // wash at L=0.96 on near-black paper is a glowing white slab.
            'wash_l' => '0.24',
            'wash_2_l' => '0.30',
            'edge_l' => '0.42',
            'shadow' => '0 0 0',
            'rest' => '0 1px 2px rgb(0 0 0 / 0.5)',
            'lift' => '0 16px 40px -12px rgb(0 0 0 / 0.65), 0 0 0 1px rgb(255 255 255 / 0.05)',
            'glow_opacity' => '0.22',
        ],
    ],

    /*
    | The contrast contract, enforced in ThemeTokens::build() rather than
    | trusted to anybody's eye. `text` is WCAG AA body text; `fill` is the
    | ink printed on a solid brand fill, which AA large-text allows at a
    | lower ratio and which Color::contrast() measures rather than guesses.
    */
    'contrast' => [
        'text_minimum' => 4.5,
        'fill_minimum' => 3.0,
    ],

];
