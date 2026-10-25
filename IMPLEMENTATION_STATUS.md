# Discovery Directory Implementation Status

This is a code-level inventory of the discovery-directory work. It is not a claim that the full brief is complete or that the current UI has been verified in a browser. The landing visual pass and shared merchant-section motion layer are now implemented; the remaining gaps below are product-depth items rather than missing foundations.

## Implemented

- Platform design tokens are defined in `config/platform.php` and exposed by `resources/views/layouts/platform.blade.php`.
- The platform homepage has been changed to an image-led discovery-directory landing page in `resources/views/pages/landing.blade.php`, with a category marquee, real listing previews, assistant showcase, sticky story, merchant counters, live subscription cards, trust and FAQ sections.
- The landing controller supplies published directory stores, products, services and categories through `app/Domains/Directory/Http/Controllers/PlatformLandingController.php`.
- Public directory routes and views exist for stores, products, services, categories and store profiles:
  - `routes/web.php`
  - `app/Domains/Directory/Http/Controllers/PublicDirectoryController.php`
  - `app/Domains/Directory/Services/DirectoryCatalogQuery.php`
  - `resources/views/pages/directory/catalog.blade.php`
  - `resources/views/pages/directory/store.blade.php`
- Product and service directory visibility fields are defined by `database/migrations/2026_10_24_090000_add_directory_listing_flags.php`.
- Outbound redirects, UTM parameters and directory-click events are implemented in `DirectoryRedirectController.php` and `RecordStoreEvent.php`.
- A basic merchant directory-traffic summary is present in `AnalyticsSummary.php` and `resources/views/dashboard/analytics/index.blade.php`.
- The Help me buy panel, endpoint, intent/rank AI task definitions and prompts exist in `resources/views/components/directory/help-me-buy.blade.php`, `MarketplaceAssistantController.php`, `config/ai.php` and `resources/prompts/ai/v1/`.
- Admins can approve, remove, feature and unfeature store directory listings from the admin Stores page.
- A queued embedding-index sync job is connected to store, product and service model saves/deletes.
- `DIRECTORY.md` and `ANIMATIONS.md` describe the intended architecture and animation conventions.

## Not done or still incomplete

### Landing page

- The landing now uses polished editorial photography and a fuller visual rhythm. The three image URLs are remote Unsplash assets and should be replaced with owned/licensed CDN assets before launch if deployment policy requires it.
- There is no testimonials carousel. Store review components exist for merchant storefronts, but they have not been assembled into a platform landing carousel with real, approved review content.
- The merchant section reads live plan cards through `PricingPlans::cards()`; sticky mobile search remains a small-screen enhancement for a later pass.

### Scroll animations and merchant themes

- `resources/js/animations.js` now auto-enrolls merchant sections and staggered groups, so theme pages no longer reveal as one large block.
- Theme-specific motion personalities remain config-driven through `data-motion`, with merchant `reduce_motion` respected.
- The platform includes category marquee, parallax, sticky scroll storytelling, counters, navbar-on-scroll state and low-data handling. Text reveal and page transitions remain intentionally lightweight rather than introducing a second dependency.
- The shared animation foundation is **DONE**; deeper per-block art direction remains an optional polish pass.
- Merchant-controlled animated backgrounds are now available through Storefront settings and the shared theme layout.
- Public directory cards now use large product media, consistent internal padding, mobile filter disclosure, city/verified filters, and WhatsApp handoff actions.
- Help me buy now caches intent and grounded ranking responses, uses configured provider fallbacks, supports cheapest/highest-price sorting, verified labels, comparison of up to three candidates, and tracked WhatsApp redirects.
- The application root type scale is set to 95% in `resources/css/app.css` so public, dashboard, auth and merchant surfaces share a compact but readable density.

### Directory and search

- Directory pages now include the mobile filter drawer, category selection across listing types, city/state/price/sort/verified filters, nearest sorting and a visible loading state.
- Search is database keyword search. Embedding indexing is optional, but embeddings are not used to retrieve and rank cross-store products/services in the directory.
- The main search does not yet call AI intent extraction; AI intent currently belongs to the Help me buy endpoint.
- Product/service canonical tags point at each merchant’s item URL and directory listings emit per-item Product/Service structured data.
- Store and item-level reporting is connected from directory cards into the store report form and admin queue.

### Help me buy

- The assistant retrieves database records and limits AI ranking to those candidates.
- Follow-up refinement, comparison, cheapest/highest-price sorting and verified labels are implemented inside the assistant.
- Results provide a WhatsApp shortcut.
- The anonymous daily counter uses the configured cache store; production behavior depends on that store being shared and persistent across app instances.

### Tracking and analytics

- The dashboard shows directory click totals, source counts, top searches, a daily time-series, most-viewed products/services and the AI analytics digest.
- Click deduplication is a simple ten-minute visitor/store check, not a full bot-detection system.

## Files to revisit first

- `resources/views/pages/landing.blade.php` — people imagery, richer sections and real testimonials carousel.
- `resources/js/animations.js` and `resources/css/storefront-theme.css` — visible per-section reveals throughout every merchant theme.
- `resources/views/components/themes/shared/layout.blade.php` and theme block components — animation opt-in coverage.
- `app/Domains/Directory/Services/DirectoryCatalogQuery.php` — complete filters and cross-store retrieval.
- `app/Domains/Directory/Http/Controllers/MarketplaceAssistantController.php` and `resources/views/components/directory/help-me-buy.blade.php` — follow-ups, comparison, sort and contact actions.
- `app/Domains/Analytics/Services/AnalyticsSummary.php` and `resources/views/dashboard/analytics/index.blade.php` — referral details and AI digest integration.

## Current state

The directory foundation and first-pass pages are present. The premium landing design, visible scroll behavior across merchant themes, and several discovery/assistant details remain unfinished. The earlier implementation instructions explicitly excluded browser checks and tests, so this inventory records implementation status without claiming runtime validation.
## Launch completion updates

- DONE: Database-backed cache and queue configuration retained; Redis is not required.
- DONE: Featured directory placement is gated by configured Growth/Pro subscriptions and stale placements are scheduled for removal.
- DONE: Product/service-level directory reports and moderation storage.
- DONE: Directory ItemList/Product/Service structured data with merchant-owned outbound URLs.
- DONE: Nearest sorting using optional latitude/longitude query coordinates.
- DONE: Local SVG production assets replace landing and design-system Unsplash dependencies.
- DONE: Queue worker Supervisor and scheduler deployment definitions.
- DONE: AI failures, queue failures and webhook dispatch failures are logged for production monitoring.
- TODO(user): Set real AI provider secrets in production; no secrets were available in the workspace.
