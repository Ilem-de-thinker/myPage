# MyPage.ng themes

The storefront engine remains config-driven. `config/themes.php` owns the
editable block schema, page order, font pairing, business-type support, and the
six locked visual directions:

- Editorial Luxe
- Minimal Swiss
- Bold / Soft
- Marketplace Catalog
- Boutique Warm
- Tech / Pro

The existing Store and Service engines still own commerce and booking behavior.
The selected `theme_preset` is stored in `store_pages.settings`; templates read
only semantic tokens such as `var(--brand)`, `var(--brand-wash)`, `var(--ink)`
and `var(--rule)`. Merchant color never appears as a literal in theme markup.

`App\Shared\Support\Color` and `ThemeTokens` are the single palette path. They
produce the merchant brand, contrast-safe ink, 50–900 brand scale, tinted
surfaces, edges, focus ring and brand-tinted elevation. The theme preview is at
`/theme-preview` and supports live color, preset, Store/Service mode, and
360px, 390px, 430px, tablet, and desktop frame switching.

Both engines support optional merchant-editable FAQ blocks. They render as
native disclosure panels and contribute FAQ structured data when all rows have
both a question and an answer. Product detail galleries use the uploaded image
set as interactive, keyboard- and touch-safe galleries.

On narrow screens, the sticky conversion bar provides WhatsApp, native share,
and phone actions when the merchant has a contact number; cart and booking
actions remain part of the page-specific purchase or reservation surface.

To add a direction, add a config preset, its locked font pair, and scoped
`data-theme-preset` rules in `resources/css/storefront-theme.css`. Do not add a
raw brand color to a theme template.
