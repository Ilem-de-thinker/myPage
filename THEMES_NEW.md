# MyPage.ng Themes — Implementation Status

This document records the theme, mobile, and PWA work completed so far, what is
still incomplete, and the assumptions used while extending the existing Laravel
theme engine.

## Progress log

- **Started:** The first preset pass was reviewed and rejected as insufficient:
  it changed mostly spacing, radii, and typography while leaving the original
  Store/Service compositions intact.
- **In progress:** Replacing preset-only styling with distinct theme
  compositions, beginning with purpose-built storefront hero structures and
  then continuing through navigation, cards, detail pages, and service pages.
- **Completed in this pass:** Added four real Store hero compositions: an
  editorial image-led split, a Swiss/tech structured grid, a bold block-based
  composition, and a dense catalog-first composition. Editorial Luxe and
  Boutique Warm share the image-led foundation but receive different finish
  rules; Minimal Swiss and Tech / Pro share the structured foundation but use
  different density and type treatment.
- **Completed in this pass:** Added three Service hero compositions with
  service-specific booking actions and information hierarchy, plus distinct
  navigation styling and mobile drawer treatment for the preset families.
- **Completed in this pass:** Added distinct footer compositions, service
  gallery rhythms, and explicit composition/font metadata in the theme
  registry.
- **Completed in this pass:** Added preset-aware detail-page treatments for
  product and service pages, including editorial image-led purchase panels,
  Swiss/Tech rule-led panels, Bold block framing, and denser Catalog spacing.
- **Completed in this pass:** Added distinct footer compositions and service
  gallery rhythms, including editorial feature spans, Bold framed tiles, and
  mobile swipe galleries.
- **Completed in this pass:** Removed the remaining hard-coded WhatsApp green
  and merchant brand literals from the inspected theme surfaces; those
  storefront surfaces now use merchant tokens. Fixed status/rating colors remain
  separate semantic UI colors and are not merchant branding.
- **Feature pass complete:** The six premium directions now have distinct hero,
  navigation, collection, detail, footer, and font decisions; responsive and
  browser verification remains a separate pass.

## Original implementation brief

The work was requested with the following requirements:

> Rework the existing MyPage.ng themes, add new ones to a premium
> "million-dollar marketplace" standard, enforce a native-app-quality mobile
> experience, and implement PWA support for the MyPage main site and every
> merchant store/business site.
>
> Audit the current theme engine, existing themes, shared components, merchant
> settings schema, and tenant routing first. Reuse the existing theme engine,
> avoid unrelated backend changes, and keep the implementation split into
> logical files.
>
> Themes must use merchant-driven colors. No hard-coded merchant brand or
> accent colors may appear in templates or styles. Build one palette generator
> with a 50–900 scale, automatic AA-safe contrast color, accent, tinted
> surfaces, borders, focus rings, and brand-tinted shadows. Test it with deep
> green, bright yellow, navy, hot pink, black, and orange.
>
> Restyle the existing themes to a premium standard and add at least six
> structurally distinct themes covering Editorial/Luxe, Minimal/Swiss,
> Bold/Brutalist-soft, Marketplace/Catalog, Boutique/Warm, and Tech/Pro.
> Themes should support Nigerian business examples, prices in naira, strong
> typography, deliberate grids, considered image treatment, refined
> interactions, reduced-motion support, and light-first responsive design.
>
> Enforce a mobile-first experience at 360px, 390px, and 430px, including
> touch-safe controls, mobile navigation, swipeable content, safe areas,
> dynamic viewport handling, lazy images, stable dimensions, and mobile sticky
> actions for WhatsApp, call, share, cart, or enquiry.
>
> Add installable PWAs for the platform and each merchant. Provide dynamic
> manifests, merchant branding, icons, correctly scoped service workers,
> offline pages, safe caching, update notifications, Android install prompts,
> iOS installation guidance, standalone styling, and online/offline status.
> Do not cache private dashboard, admin, authenticated, API, or POST data.
>
> Register the themes in the existing config-driven system, include theme
> metadata, add a live theme preview with brand-color testing and a mobile
> frame, and document the implementation in `THEMES.md` and `PWA.md`.
> Verify contrast, responsive layouts, installability, offline behavior, and
> Lighthouse/mobile performance before finishing. State assumptions and any
> remaining backend work.

The complete original request also required that merchant support in themes
include WhatsApp conversion actions, store contact/reporting entry points,
semantic markup, visible focus states, realistic merchant content, and a
proper product or service detail page.

## Completed

### Theme engine and visual directions

- Preserved the existing `Store` and `Service` theme engines, routes, block
  schemas, product pages, booking pages, and tenant resolution.
- Restyled the existing Store and Service theme surfaces through the shared
  layout, headers, footers, hero sections, product/service cards, contact
  sections, review sections, and storefront controls. The restyling uses the
  new semantic merchant tokens and responsive theme-direction rules.
- Added six selectable visual directions in `config/themes.php`:
  - Editorial Luxe
  - Minimal Swiss
  - Bold / Soft
  - Marketplace Catalog
  - Boutique Warm
  - Tech / Pro
- Added business-type metadata and locked heading/body font pairings for each
  direction.
- Added a merchant-facing theme direction selector to the storefront settings
  page.
- Locked each preset to its registered font pairing and filtered the selector by
  business type, so Store and Service pages cannot receive incompatible
  directions.
- Added `/theme-preview` with live theme switching, live brand-color input, and
  360/390/430px, tablet, and desktop frame modes, separate Store/Service
  preview modes, and selected-preset composition/font metadata.
- Added scoped visual rules to
  `resources/css/storefront-theme.css` so the directions change layout rhythm,
  spacing, borders, card treatment, typography, and density rather than only
  changing colors.
- Added mobile behavior to the shared storefront surfaces, including responsive
  navigation, horizontal content scrolling where applicable, thumb-reachable
  actions with 44px touch targets, floating support access, and merchant
  WhatsApp conversion paths, plus native share and call actions in the mobile
  sticky bar.
- Added purpose-built Store hero markup rather than depending only on preset CSS:
  `hero-editorial`, `hero-structured`, `hero-brutalist`, and `hero-catalog`.
- Added purpose-built Service hero markup: `hero-editorial`,
  `hero-structured`, and `hero-brutalist`.
- Added preset-aware navigation treatments so the theme direction changes the
  header rhythm and mobile drawer behavior, not just card borders.
- Added explicit composition metadata and preset-specific font loading in the
  theme registry so hero, navigation, collection, detail, footer, and font
  decisions are represented in configuration rather than only in CSS.
- Added preset-aware product/service detail wrappers and styling so detail pages
  do not fall back to one universal purchase/booking composition.
- Added preset-aware collection rhythms: editorial cover-story grids, dense
  Catalog browsing, Bold poster-like offsets, Tech service columns, and
  mobile swipe behavior for image-led collections.
- Re-ran the merchant-brand literal search; no `#25D366` or arbitrary merchant
  brand color remains in the inspected theme templates/components. Success,
  warning, error, and rating states now use semantic tokens too, so storefront
  status colors adapt with the selected light/dark surface.
- Existing Store and Service templates continue to provide the full page
  surfaces and commerce/booking behavior: navigation, heroes, listings,
  featured content, about, gallery/reviews, FAQ/contact areas where supported,
  footer, and detail pages.
- Added optional merchant-editable FAQ blocks to both Store and Service home
  pages, rendered as accessible native disclosure panels.
- Added matching FAQ structured data to storefront JSON-LD when a FAQ block has
  complete question-and-answer rows.
- Added stable image dimensions and lazy-loading coverage to service cards,
  galleries, and detail surfaces to reduce mobile layout shift.
- Added interactive product image galleries with keyboard- and touch-safe
  thumbnail controls.

### Merchant-driven color system

- Added a central palette generator to `App\Shared\Support\Color`.
- Added a 50–900 brand scale generated from the merchant's selected color.
- Added contrast-aware brand ink selection for light and dark brand colors.
- Added brand-derived text-safe colors, tinted surfaces, rules, focus rings,
  and shadows through `ThemeTokens`.
- Updated theme styling to consume semantic CSS variables such as
  `--brand`, `--brand-ink`, `--brand-wash`, `--rule`, and `--ink`.
- Kept platform-only MyPage branding separate from merchant branding.

### PWA foundations

- Added platform PWA endpoints:
  - `/manifest.webmanifest`
  - `/service-worker.js`
  - `/offline`
  - `/pwa/icon/192`
  - `/pwa/icon/512`
- Added equivalent merchant endpoints on each merchant origin.
- Added dynamic merchant manifest values for business name, description,
  brand color, background color, scope, portrait orientation, and icon URLs.
- Added branded 192px and 512px PNG icons for merchants and the platform shell;
  uploaded merchant logos are rendered into a branded icon canvas, with a
  monogram fallback when no logo is available.
- Added service-worker caching rules for public pages and assets:
  - network-first HTML with offline fallback;
  - stale-while-revalidate CSS, JavaScript, fonts, and images;
  - versioned caches and cleanup during activation;
  - no caching for POST requests, authenticated requests, dashboard/admin
    pages, API paths, login, or registration.
- Added branded offline page templates with merchant contact information when
  available.
- Added shared layout metadata for manifest links, theme color, Apple PWA
  support, and Apple touch icons.
- Added a delayed install prompt with Android/Chrome install support,
  iOS Share → Add to Home Screen guidance, dismissal persistence, standalone
  detection, and an update/refresh toast.
- Added `PWA.md` with the manifest contract, cache rules, scope behavior, and
  local installation test instructions.

### Related storefront work already present

- Added merchant map coordinates and the Google Maps picker/search flow in the
  seller settings area.
- Added dynamic host/domain handling so development and migration do not depend
  on a hard-coded `mypage.test` or `mypage.ng` host.
- Added support ticketing with separate ticket pages and conversation views for
  sellers and platform administrators.
- Added storefront support actions for contacting the store and reporting a
  store/merchant to MyPage.
- Added floating support entry points and dark-mode-safe support controls.

## Incomplete or requiring follow-up

### Required fixes before calling the PWA work complete

- Browser verification is still required for install prompts, offline reloads,
  update prompts, standalone mode, and Lighthouse PWA scoring.
- The current worker is root-scoped on each origin. Custom-domain forwarding
  is supported, but path-based tenant deployments would require a worker route
  served from the tenant directory or an explicitly verified
  `Service-Worker-Allowed` setup.

### Theme quality verification still required

- The automated palette contrast check covers deep green, bright yellow, navy,
  hot pink, black, and orange; browser contrast inspection remains pending.
- Search all theme templates and storefront CSS for remaining brand-color
  literals and replace any remaining WhatsApp/accent literals with semantic
  tokens. Fixed platform neutrals and status fallbacks may remain where they
  are genuinely neutral.
- Verify each theme at 360px, 390px, 430px, tablet, and desktop widths.
- Confirm there is no horizontal overflow, clipped content, or touch target
  smaller than 44px.
- Confirm every existing Store and Service detail route renders correctly with
  every supported preset.
- Run the production asset build and inspect the generated CSS/JavaScript in a
  browser on a low-bandwidth mobile profile.

### Product/design follow-up

- The existing themes have been restyled, and six visual directions have been
  created as registered presets with distinct compositions layered over the two
  shared structural engines. Six independent engine directories are not
  necessary because they would duplicate commerce and booking behavior.
- Preset font pairings are now locked to the selected direction. Legacy saved
  font overrides are ignored once a valid preset is selected.
- Portfolio/gallery content remains dependent on the existing Store/Service
  block schemas; additional schema blocks should be added only if the product
  requires merchant-editable content beyond what is already available.

## Verification status at the time of writing

| Area | Status |
| --- | --- |
| Existing Store/Service theme engine preserved | Done |
| Six visual directions registered | Done |
| Live theme preview page | Done |
| Merchant palette/token pipeline | Implemented; automated contrast coverage added |
| Platform manifest and service worker | Implemented |
| Merchant manifest and service worker | Implemented |
| Branded offline page | Implemented |
| Install prompt and iOS guidance | Implemented; browser verification pending |
| PNG merchant icon generation | Implemented |
| Full responsive/device verification | Pending |
| Lighthouse PWA/performance verification | Pending |

## Assumptions

- Tenant URLs remain governed by the existing subdomain/custom-domain routing;
  no new tenancy model was introduced.
- A merchant's selected `brand_color` remains the source of truth for merchant
  theme tokens. The default brand color is used only when no merchant color is
  configured.
- Platform MyPage branding may use its own fixed platform palette because it is
  not merchant content.
- Existing Store and Service structures are more important to preserve than
  introducing six duplicated template trees.
- PNG icon generation uses the existing GD extension and the uploaded logo from
  the public storage disk; stores without a logo use a monogram fallback.

## Next recommended order

1. Run the focused PWA, support, storefront smoke, and theme-token tests.
2. Run Pint and the frontend production build.
3. Remove any remaining merchant brand literals from theme surfaces. (Done for
   the current theme surface inventory; the search should be repeated when new
   blocks are added.)
4. Perform browser checks at the required mobile widths and test installation
   and offline behavior on platform and merchant origins.
5. Continue with browser and responsive verification after the feature pass.

