# MyPage.ng public platform design

## Direction

The public platform uses a light-first Nigerian editorial direction: warm paper, deep ink, forest signal green and terracotta ember. The visual language is a discovery desk rather than a marketplace: a visitor sees a real listing, understands the handoff, and continues to the merchant's own store.

Typography is locked to Fraunces for display moments and DM Sans for interface copy. Local fallback stacks are declared in `resources/css/platform-tokens.css` and the platform layout loads both families with `display=swap`.

## Token source

All platform colors, type, spacing, radii, shadows and motion values live in `resources/css/platform-tokens.css`. `config/platform.php` supplies server-rendered token values to the shell. Legacy `paper`, `ink`, `brand`, `surface` and `rule` aliases are intentionally mapped back to platform tokens so older public components remain visually consistent.

## Shared motion

The lightweight IntersectionObserver layer in `resources/js/animations.js` is used instead of AOS or GSAP. It has no new dependency and keeps the first load small.

Use declarative attributes in Blade:

```blade
<article data-reveal="clip-curtain" data-delay="80" data-duration="700">...</article>
<div data-stagger>...</div>
<strong data-counter="1400" data-counter-suffix="+">0</strong>
<div data-parallax="0.03">...</div>
```

Supported reveal types include the default rise, `fade`, `scale`, `clip-curtain` and `mask-line`. `--stagger-index` controls child delay. Route paths use `.platform-route-path` and draw when observed. `.btn-magnetic` enables the subtle desktop pointer pull. The top progress line uses `[data-scroll-progress]`.

Animations pause when a decorated element leaves the viewport and when the tab is hidden. Reduced motion, reduced data and Save-Data disable decorative motion while keeping all content visible.

## Background elements

`<x-platform.background-field />` composes the grain, dot grid, ghost type, route line and cursor spotlight. Individual primitives live in `resources/views/components/platform/bg/`. They are pointer-transparent and use only transforms, opacity, SVG and CSS gradients. The hero adds independently drifting cards; directory pages use the same field with calmer content motion.

## Public UI surfaces

- Landing: `resources/views/pages/landing.blade.php`
- Directory catalogue: `resources/views/pages/directory/catalog.blade.php`
- Store profile: `resources/views/pages/directory/store.blade.php`
- AI sheet and launcher: `resources/views/components/directory/help-me-buy.blade.php`
- Support launcher: `resources/views/components/support/floating-button.blade.php`
- Public card variants: `resources/views/components/platform/cards/`

Cards use distinct product, service and store compositions. Missing media uses `visual/placeholder.blade.php`, which renders an illustrated silhouette instead of a broken image.

## Merchant theme motion

The shared merchant layout includes `themes/shared/animated-background.blade.php`. Theme personalities are selected by the existing `motion_personality` setting: editorial is slow and spacious, boutique uses soft rises, brutalist uses sharp compact movement, and tech is quick and precise. `reduce_motion` disables the background layer while the merchant color tokens continue to come from the merchant settings.

## Performance and accessibility

Motion is CSS/IntersectionObserver only. Media has dimensions and lazy loading where it is not the hero asset. Mobile uses snap rails and thumb-zone controls. Focus rings remain visible, icon buttons have labels, and reduced-motion visitors receive static cards and fully visible content.
