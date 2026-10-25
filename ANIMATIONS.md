# Shared animation layer

The application uses a small IntersectionObserver module in `resources/js/animations.js` instead of a heavier animation dependency. It keeps the Blade asset pipeline small and fails open: without JavaScript, content remains visible. This is intentionally lighter than AOS for a phone-first Blade site: there is no vendor bundle, no scroll-loop work, and only transform/opacity are animated.

## Declarative usage

Add `data-reveal` to any element. Supported values are the default rise, `fade`, and `scale`. Add `style="--reveal-delay: 120ms"` for a deliberate delay. Put children inside `data-stagger` to stagger them automatically. Use `data-counter="1200"` for a number counter and `data-parallax="0.04"` for restrained parallax. The observer only animates opacity and transform.

The platform landing uses these attributes for the hero, category rail, directory cards and story sections. Merchant sections are enrolled automatically from `#main-content` when they do not declare an attribute, so every theme receives visible per-section motion through the shared layout.

Merchants can enable `Animated storefront background` in Storefront settings. It adds a small, low-contrast field of moving rings and points behind the page, using only `var(--brand)` and derived theme tokens. It is off by default, stored as `settings.animated_background`, and is not rendered when the merchant selects reduced motion.

## Theme personalities

The merchant body reads `settings.motion_personality`: `editorial` is slow and elegant, `boutique` is soft and spacious, `brutalist` is short and decisive, and `tech` is crisp and quick. The default is editorial for stores and boutique for services. Merchants can set `settings.reduce_motion` to disable the effects.

The platform landing also uses a restrained category marquee, hero parallax, sticky story column, counters, navbar-on-scroll state, and moving background objects. `prefers-reduced-motion`, `prefers-reduced-data`, and the merchant setting immediately show all content, stop the marquee/background objects, and remove transitions. Animation does not control content availability, and no merchant color is introduced by the animation layer.
