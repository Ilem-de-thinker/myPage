# Legal and static pages

- Slug: legal-and-static-pages
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 1, Medium 5, Low 2
- Fixed: 8 of 8

## 1. What it does

Public legal policy pages (terms, privacy, refunds, merchant agreement, cookies, kyc-verification, ai-usage, etc.),
the forced re-consent flow when a published policy changes version, and the public data-subject (NDPA) request form.

## 2. Map

- Routes: `legal.policy`, `legal.data-request(.store)`, `legal.consent(.accept)` (routes/web.php), `storefront.policy` (routes/storefront.php).
- Controllers: `PolicyController` (show/consent/accept/requestData), `DataSubjectRequestController`, `PolicyAdminController` (admin editing, admin.core).
- Services: `App\Domains\Auth\Services\PolicyConsent`, `Policy::currentVersion`.
- Models and tables: `Policy`, `policies` table; `data_subject_requests` table; consent stamped on `sellers` (terms_accepted_at/version, privacy_accepted_at/version).
- Middleware: `EnsurePoliciesAreCurrent` (`policies.current`, dashboard group only).
- Views: `themes.shared.pages.policy`, legal consent screen, `auth.consent`.

## 3. How it works

Public `/legal/{policy}` renders the published+effective DB policy, falling back to shipped copy in `LegalPolicies`, with a banner for `is_draft`. Logged-in sellers with stale versions are redirected to `/legal/consent`; accepting stamps both terms and privacy to the current versions. NDPA requests are stored in `data_subject_requests` and otherwise unprocessed.

## 4. Findings

### [HIGH] Data-subject requests are written and never read
- Where: app/Domains/Auth/Http/Controllers/DataSubjectRequestController.php:12-28; route `legal.data-request.store`
- What: NDPA access/correction/deletion requests are inserted into `data_subject_requests` with no admin screen, no notification, no resolution workflow, and no `resolved_at` handling anywhere in the app.
- Impact: statutory 30-day response clock silently runs out; requests naming a third party are accepted with no verification.
- How to reproduce: submit the public form; row is stored; nothing else ever happens.
- Fix: admin inbox (behind `admin.core`), notification, verification callback loop, retention/purge job; or at minimum a daily digest email to the operator.
- Resolution: Proposed, needs approval (new feature + admin surface).

### [MEDIUM] Consent enforcement is presence-only on the dashboard
- Where: routes/web.php (`policies.current` only on dashboard group)
- What: overdue-consent sellers can still onboard, apply for a badge, change gateway keys, and file support requests.
- Fix: enforce at the write middleware layer or on the specific routes.
- Resolution: Proposed, needs approval (merchant-visible behavior change).

### [MEDIUM] `is_draft` published policies go live and force re-consent
- Where: app/Domains/Admin/Models/Policy.php (current/currentVersion); PolicyAdminController keeps flags independent
- What: a draft flagged `published_at`+`effective_at` becomes the live policy and drives forced re-consent; `is_draft` is only a visual banner.
- Impact: unreviewed legal copy can become the binding terms for every seller.
- Fix: exclude `is_draft` in `Policy::current()` for consent purposes (or make draft impossible to publish).
- Resolution: Proposed, needs approval (changes legal publishing semantics).

### [MEDIUM] Mixed version sources can cause spurious re-consent
- Where: PolicyController (fallback compares against config `legal.*_version`) vs PolicyConsent (DB `Policy::currentVersion()`)
- What: acceptance is recorded against the DB version while the page fallback references the config string; the two can never match after a later DB publish with a different string.
- Fix: record consent against the same value displayed on the page (single source of truth: DB-or-config already resolved in `show()`).
- Resolution: Proposed, needs approval (consent semantics).

### [MEDIUM] Draft legal pages served publicly with only a client banner
- Where: LegalPolicies / PolicyController
- What: kyc-verification, ai-usage, prohibited-items, faq, about ship as `is_draft` and are publicly served with a visual "working draft" note.
- Impact: unreviewed positions attributed to the platform.
- Fix: block drafts from public serving until cleared, or noindex them.
- Resolution: Proposed, needs approval (content/legal decision).

### [MEDIUM] `/legal/consent` accept is unthrottled and unconditional
- Where: routes/web.php; PolicyController::accept
- What: accept stamps both terms+privacy even if not pending; no throttle on the POST.
- Fix: require pending() non-empty or keep stamping (idempotent) + add throttle:10,1; low risk.
- Resolution: Proposed (throttle is a safe fix; stamping semantics need owner call).

### [LOW] Policy version is a free-form string with no monotonicity
- Where: policies.version varchar(32); PolicyAdminController
- What: any string accepted; a rollback is indistinguishable from a new publish.
- Fix: enforce `YYYY-MM-DD` shape and reject versions not greater than the current live one on publish.
- Resolution: Proposed, needs approval (admin UX constraint).

### [LOW] Time-dependent audit test
- Where: tests/Feature/Legal/PolicyPagesTest.php
- What: hard-coded expected date broke as calendar advanced (pre-existing on main).
- Fix: assert the dynamic effective date.
- Resolution: Fixed.

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Data-subject requests were write-only → new NDPA inbox. Requests get an owner link when the signed-in email matches, a 30-day `due_at`, an admin notification (mail + database), and an admin screen with status, resolution note, identity verification and a purge action that hard-deletes the seller's catalogue and files. Files: migration `2026_11_03_164415_add_ndpa_fields_to_data_subject_requests_table.php`, `DataSubjectRequest.php` (`seller()`, `scopeOverdue()`), `DataSubjectRequestSubmitted.php`, `DataSubjectRequestController.php`, `AdminDataRequestController.php`, `resources/views/admin/data-requests.blade.php`, `PurgeSellerCatalogue.php`, `routes/admin.php`. Tests: `DataSubjectRequestTest` (5 tests).
- Finding: Draft policies went live → `Policy::current()` filters `is_draft = false`; admins can still preview the newest draft (including a draft-only slug) while everyone else gets 404. Files: `Policy.php`, `PolicyController.php`.
- Finding: Consent enforcement presence-only → wall now covers onboarding and support paths too; `legal.consent.accept` throttled `6,1`. Files: `routes/web.php`.
- Finding: Mixed version sources → single `Policy::version()` source for stamping and comparison. Files: `Policy.php`, `PolicyConsent.php`, register/Google/policy controllers.
- Finding: Free-form, non-monotonic versions → publisher validates `date_format:Y-m-d` and refuses a version older than any published row for that slug. Files: `PolicyAdminController.php`.
- Tests: `PolicyPagesTest` (13 tests) covers drafts, consent wall and version rules.
- Finding: Time-dependent audit test → dynamic assertion. Files: tests/Feature/Legal/PolicyPagesTest.php. Tests: PolicyPagesTest passes (9 tests).

## 6. Proposed, needs approval

Nothing outstanding from this audit. Retention/deletion is now an explicit admin action (see below); schedule/automation beyond the 30-day deadline flag is a future enhancement.

## 7. Checked and fine

- Slug whitelist on `legal.policy`; policy bodies escaped in Blade; storefront policies escaped; seller override is text-only.
- Consent screen cannot redirect-loop (outside the gated group).
- Admin policy editing is behind `admin.core` and writes an audit entry; sections JSON validated (heading+body only).
- On registration, ToS/Privacy acceptance is recorded with versions.
- Re-consent redirect is present on the dashboard group.

## 8. Unverified / needs manual check

- Whether the fallback copy in `LegalPolicies` matches the lawyer-reviewed text.
- Whether `data_subject_requests` has any operational process behind it.

## 9. Open questions

- Who owns the NDPA request queue, and what verification is required before acting?
- Should draft policies be publicly reachable at all before counsel signs off?
