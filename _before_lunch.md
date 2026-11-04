# MyPage.ng — final pre-launch status

**Date:** 2026-11-03
**Purpose:** reconciled record of what is left before launch, after the feature audit (2026-11-03, 988 tests passing across Admin/Ai/Auth/Billing/Booking/Catalog/Seller/Tenancy).

Audit status: all 12 feature areas reviewed. Every Critical/High finding is fixed or explicitly deferred by the owner. Remaining open items are low/medium except the deferred platform-budget accounting for page-less AI calls.

---

## Done (high level)

- Feature audit complete: merchant onboarding/auth, legal, products, categories, reviews, billing, bookings, AI credits, AI product, AI photo, AI task, AI categories — all Working/Partial with findings fixed.
- Money path hardened: manual transfers Pending + admin approval, webhook-first first payment activation, recurring renewal processing, NGN currency check, Flutterwave verif-hash, fail-closed plan limits, locked transactions.
- Security: `Html::sanitize` full tree-walk, cross-tenant category scoping, soft-deletes + 30-day purge, variant SKU per-store, image cap 8, AI staging on private disk with authorized route, provider payload downscale to 1600px webp q80, input byte caps, media path allowlist.
- AI: sync credit reservation under seller lock, buyer assistants platform-funded with per-store daily cap, cancelled ops released, retry-failed/cancel throttled.
- Legal: NDPA inbox with admin screen, draft policies excluded from consent, consent wall on onboarding/support, version monotonicity.
- Tests: 988 passing; pint clean.

---

## What is left

### A. External / owner actions (blocking real money)

- [ ] Create the three Paystack plans (Starter ₦3,500 / Growth ₦9,500 / Pro ₦25,000 monthly) and set `PAYSTACK_PLAN_STARTER/GROWTH/PRO`.
- [ ] Register the Paystack webhook URL in the dashboard (`https://YOUR-DOMAIN/webhooks/paystack`); Flutterwave too if used.
- [ ] Run a queue worker under Supervisor/systemd/Laravel Cloud.
- [ ] Install live payment keys; rotate the previously disclosed test keys.
- [ ] Set `APP_URL=https://mypage.ng`.
- [ ] Record the legal entity and complete NDPC registration.
- [ ] Configure the live mailer and publish SPF, DKIM, DMARC.
- [ ] Issue the wildcard `*.mypage.ng` certificate and custom-domain TLS.
- [ ] Provide real AI provider credentials.

### B. Code / ops still open (deferred or verification)

- [ ] Verify legal pages, consent capture, DSAR endpoints and NDPC wording against the registered entity.
- [ ] Verify admin can open the KYC document they are asked to review.
- [ ] Verify the seller-facing "webhook not configured" warning.
- [ ] Verify unverified-seller warning on page cards, checkout and order screens.
- [ ] Verify one report reaches the admin queue and remains hidden from the seller.
- [ ] Decide and implement the trial treatment for card signups (B1).
- [ ] Add Horizon/Sentry/uptime/backups.
- [ ] Review page-cache hit rate and N+1 queries.
- [ ] Refresh demo seed pages and prepare private beta rollout.
- [ ] `ai.plans` config keys for starter/growth/pro (config debt, shared with billing).
- [ ] Platform-budget accounting for page-less site_builder / marketplace assistant calls (M, deferred).
- [ ] AI staging orphan sweeper (S).
- [ ] Batch slot-cap lock + hard global `max_active_operations` cap (S).
- [ ] `running`-op sweeper / watchdog (S).
- [ ] Slot-cap re-check in `retryFailed` (S).
- [ ] Cap/summarise catalogue slice fed to category AI (S).
- [ ] Eager-load category in description generation (S).
- [ ] `AvailabilityService` batch loading (M, deferred).
- [ ] Deposit naira→kobo truncation via `Money::toKobo` (S).
- [ ] Booking notification times in wrong timezone (S).
- [ ] Enforce Google `email_verified` claim for account linking (S).
- [ ] Schedule/automation beyond the 30-day NDPA deadline flag (future enhancement).

### C. AI test gaps (deferred, not blockers)

- [ ] One failed batch item does not block remaining drafts.
- [ ] Credit limit reached mid-batch keeps completed drafts and shows upgrade path.
- [ ] Nothing saved before Confirm; undo restores previous state.
- [ ] Floating AI launcher renders only on pages registered in `AiFeatureRegistry`.
- [ ] No central `/ai` page or navigation entry remains (redirect only).
- [ ] End-to-end verification of store creation, products, services, appearance, content, analytics, order chat, buyer assistant flows.

### D. Verification before release

- [ ] `php artisan view:cache` / `view:clear` / `npm run build` / `vendor/bin/pint --dirty --format agent`.
- [ ] Owner runs full suite: `php artisan test --compact`.
- [ ] Hand-check: landing at 360/390/tablet/desktop; directory with seeded + empty DB; store profile empty catalogue; Help Me Buy success/no-results/429/comparison; support open/close/escape/backdrop/keyboard; PWA install + SW update toast; merchant storefront with animated background on/off.

### E. Deferred (not blockers)

- Sanctum API.
- WhatsApp Cloud API messaging.
- Full TLS edge for custom domains beyond the wildcard certificate.
- Time-off scoping / unassigned holds (informational/design).
- NDPA queue ownership and draft-policy public reachability (open questions).

---

## Real-money smoke tests (after B/C/D)

- [ ] One real ₦ subscription charge to mypage.ng, reconciled in Paystack.
- [ ] One real ₦ order paid to a seller's own Paystack account, webhook registered, order reaches `paid`, stock moves, buyer email delivered.
- [ ] One seller entering a wrong key; app refuses it.
- [ ] One buyer reporting a store; report reaches admin queue and is triaged.

---

## Notes

- Older `todo.md` phase checkboxes (Phase 0–8) are historical; many are now implemented. The feature docs under `docs/features/` and this file are the current source of truth.
- `docs/escrow.md` is historical (withdrawn 2026-10-23); escrow/wallet/payout code is to be deleted, not maintained.
- `README.md` is still the stock Laravel readme; replace before launch.
