# MyPage.ng — final pre-launch status

**Date:** 2026-10-24  
**Purpose:** one reconciled record of what is left before launch. This replaces the scattered status claims in `before lunch.md`, `todo.md`, `IMPLEMENTATION_STATUS.md`, `AI_IMPLEMENTATION.md`, `DIRECTORY.md`, `ANIMATIONS.md`, `THEMES.md` and `PWA.md`.

The project is close, but not launch-ready. The remaining work is a mix of:

- one known public-UI defect,
- directory polish,
- AI implementation/test gaps,
- production credentials, mail, legal registration and TLS,
- final verification.

Do not take real money until the launch gate at the end of this file is cleared.

---

## Current state

### Working

- Public landing page uses real published stores, products and services.
- Pricing cards read active plans through `PricingPlans::cards()`.
- Featured reviews read approved database reviews; there are no fake testimonials.
- Help Me Buy panel is database-backed and supports sorting, comparison of up to three items, WhatsApp handoff, empty states and the 429 daily-limit state.
- Support and Help Me Buy use CSP-safe `Alpine.data` registration.
- Public floating stack places Help Me Buy above Support with a consistent gap and hides while sheets are open.
- `platform.css` imports tokens, motion and components only.
- `app.css` exposes platform colours, display font and radii through Tailwind v4 `@theme`.
- Directory reads stores, products and services through `DirectoryCatalogQuery`.
- Directory redirects, UTM tags and click events are implemented.
- AI registry, batch drafts, confirm-selected, retries and contextual placement tests exist.
- Seller gateway credentials, per-tenant checkout/webhooks, store reports, store support, legal pages, consent records, DSAR endpoints and escrow/payout removal are implemented in code.
- `npm run build` and `php artisan view:cache` pass after the latest UI changes.

### Known defect

None open. `help-me-buy-showcase` now registers `helpMeBuyShowcase` through `Alpine.data`, and the remaining inline Alpine handlers were moved into `resources/js/alpine-components.js`.

---

## 1. Public UI remaining

### Landing and directory

- [x] Fix `help-me-buy-showcase` CSP failure.
- [x] Finish directory mobile filter drawer, category selection and city filtering UI.
- [x] Add directory loading state. Empty-state check still needs a real empty database.
- [x] Finish per-item Product/Service canonical tags and structured data.
- [x] Connect store/item reporting and moderation flows from directory surfaces.
- [x] Add directory analytics time-series, top items, top search queries and AI digest integration.
- [x] Remove unused `resources/views/pages/directory/index.blade.php`.
- [ ] Verify every public page at 360px, including floating stack, sheets, filters and safe areas.
- [ ] Refresh `DESIGN.md` and `IMPLEMENTATION_STATUS.md` so they describe the current implementation, not earlier drafts.

### Motion

- [x] Confirm `motion_personality` changes merchant theme motion.
- [x] Finish the animated merchant background layer and reduced-motion/reduced-data fallbacks.
- [x] Harden `resources/js/animations.js` for offscreen pause, hidden tabs, reduced motion, Save-Data, route drawing and counters.
- [x] Confirm all animation is transform/opacity only and content remains visible without JavaScript.

---

## 2. AI remaining

Source: `AI_IMPLEMENTATION.md`.

### Tests still missing

- [ ] One failed batch item does not block the remaining drafts.
- [ ] A credit limit reached mid-batch keeps completed drafts and shows the upgrade path.
- [ ] Nothing is saved before Confirm; undo restores the previous state.
- [ ] Floating AI launcher renders only on pages registered in `AiFeatureRegistry`.
- [ ] No central `/ai` page or navigation entry remains (redirect only).

### Surfaces to verify end to end

- [ ] Store creation: AI proposal, review, per-item keep/remove, regenerate, confirm, undo and version history.
- [ ] Products: photo batches, pasted lists, grouping, draft cards, select-all, confirm-selected, confirm-all, retries and partial results.
- [ ] Services: same bulk flow with packages, duration, FAQ and booking questions.
- [ ] Appearance: three theme/colour presets and logo colour suggestion, all confirmable.
- [ ] Content: rewrite and translation into English, Nigerian Pidgin, Yoruba, Igbo and Hausa.
- [ ] Dashboard/Analytics: tenant-scoped insights and ask-your-data, no raw SQL.
- [ ] Order chat: suggestions, summaries and translations remain opt-in; merchant sends the final message.
- [ ] Buyer assistant stays grounded in the current merchant’s published data and hands off to WhatsApp without colliding with floating controls.

### Production AI

- [ ] Set real provider secrets in production.
- [ ] Run `php artisan config:clear` and `php artisan ai:test-task store_assistant --input='{"message":"What do you sell?"}'`.
- [ ] Confirm fallback chain, credit ledger and failed-task logging.

---

## 3. Launch gate

### External / owner actions

- [ ] Create the three Paystack plans and supply `PAYSTACK_PLAN_STARTER`, `PAYSTACK_PLAN_GROWTH`, `PAYSTACK_PLAN_PRO`.
- [ ] Register the Paystack webhook URL in the dashboard.
- [ ] Run a queue worker under Supervisor/systemd/Laravel Cloud.
- [ ] Install live payment keys; rotate the previously disclosed test keys.
- [ ] Set `APP_URL=https://mypage.ng`.
- [ ] Record the legal entity and complete NDPC registration.
- [ ] Configure the live mailer and publish SPF, DKIM and DMARC records.
- [ ] Issue the wildcard `*.mypage.ng` certificate and custom-domain TLS.
- [ ] Provide real AI provider credentials.

### Code / operations before real money

- [ ] Verify legal pages, consent capture, DSAR endpoints and NDPC wording against the registered entity.
- [ ] Verify admin can open the KYC document they are asked to review.
- [ ] Verify the seller-facing “webhook not configured” warning.
- [ ] Verify unverified-seller warning on page cards, checkout and order screens.
- [ ] Verify one report reaches the admin queue and remains hidden from the seller.
- [x] Add the missing unprocessed-webhook sweep.
- [x] Notify sellers in-app when a subscription card expires. Email delivery still depends on the live mailer.
- [ ] Decide and implement the trial treatment for card signups (`B1`).
- [ ] Keep plan creation manual unless explicitly changed (`B2`).
- [ ] Add Horizon/Sentry/uptime/backups.
- [x] Remove remaining CSP-unsafe inline Alpine handlers.
- [ ] Review page-cache hit rate and N+1 queries.
- [ ] Refresh demo seed pages and prepare private beta rollout.

### Real-money smoke tests

- [ ] One real ₦ subscription charge to mypage.ng, reconciled in Paystack.
- [ ] One real ₦ order paid to a seller’s own Paystack account, with the seller’s webhook registered, order reaching `paid`, stock moving and buyer email delivered.
- [ ] One seller entering a wrong key; the app refuses it.
- [ ] One buyer reporting a store; the report reaches the admin queue and is triaged.

---

## 4. Deferred, not blockers

- Sanctum API.
- WhatsApp Cloud API messaging.
- Full TLS edge for custom domains beyond the wildcard certificate.

---

## 5. Verification before release

Run and record:

```bash
php artisan view:cache
php artisan view:clear
npm run build
vendor/bin/pint --dirty --format agent
```

Then ask the owner to run the complete suite:

```bash
php artisan test --compact
```

Also verify by hand:

- [ ] Landing at 360px, 390px, tablet and desktop.
- [ ] Directory with seeded data and with an empty database.
- [ ] Store profile with an empty catalogue.
- [ ] Help Me Buy: success, no results, 429, comparison and close/reopen.
- [ ] Support: open, close, escape, backdrop and mobile keyboard.
- [ ] PWA install prompt and service-worker update toast.
- [ ] Merchant storefront with animated background on and off.

---

## 6. Open decisions

- **B1 — trial:** card signups currently skip the 14-day trial. Recommended: accept now, delete `trial_days` deliberately later.
- **B2 — plans:** keep manual Paystack plan creation. Ask before automating.
- **B3 — expiring cards:** owner requires both in-app and email notification.
- **B4 — webhook sweep:** build the daily re-dispatch for unprocessed `webhook_events`.

---

## 7. Files to revisit first

- `resources/views/components/platform/sections/help-me-buy-showcase.blade.php`
- `resources/views/pages/directory/catalog.blade.php`
- `resources/views/pages/directory/store.blade.php`
- `resources/js/animations.js`
- `resources/css/storefront-theme.css`
- `resources/views/components/themes/shared/layout.blade.php`
- `app/Domains/Directory/Services/DirectoryCatalogQuery.php`
- `app/Domains/Analytics/Services/AnalyticsSummary.php`
- `tests/Feature/Ai/ContextualAiPlacementTest.php`
- `IMPLEMENTATION_STATUS.md`
- `DESIGN.md`
