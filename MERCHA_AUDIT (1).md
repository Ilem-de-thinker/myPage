# Mercha Audit and Documentation Playbook

Audit the Mercha Laravel codebase one feature at a time. **Every feature the AI sees, reviews, or fixes gets its own documentation file.** Nothing is lumped into one big report.

## How to use this file

Give this file to your coding AI with the instruction:

> Follow MERCHA_AUDIT.md. Work one feature at a time. Create one file per feature in `docs/features/`, and keep `docs/features/INDEX.md` up to date as you go.

---

## 1. Ground rules

1. **One feature, one file.** Each feature gets `docs/features/<feature-slug>.md` (kebab-case, e.g. `ai-credits-and-limits.md`). Never put two features in one file. If a feature is large, split it (e.g. `ai-product-creation.md` and `ai-task-manager.md`).
2. **One feature at a time.** Finish documenting, reviewing, and fixing a feature before starting the next. Do not interleave.
3. **Evidence or it did not happen.** Every finding needs a file path and line range (or route name), why it is a problem, and how it can be triggered. Do not report anything you have not traced through the actual code. Put unverifiable items under "Unverified".
4. **Discover features from the code, not from assumptions.** Build the feature list from routes, controllers, views, jobs, models, config, and migrations. The suggested list in section 6 is a starting point, not a limit.
5. **Document what you changed, in the same file as the feature.** If you fix something, record it in that feature's file (what, why, files touched, tests added). A fix with no entry is not allowed.
6. **Fix rules** (what you may change without asking):
   - You MAY fix: Critical and High security issues, clear bugs, missing server-side enforcement, and missing validation, as long as the change stays inside the feature being worked on.
   - You MUST ask first (list it under "Proposed, needs approval" and do not implement): schema changes to existing tables, behavior changes visible to merchants, deleting features or routes, new dependencies, and anything touching billing amounts or webhooks.
   - Never edit migrations that may already have run. Add new ones.
7. **Verify every fix.** Run the relevant tests, add tests for the fix, and record the result. If tests cannot run, say so.
8. **Laravel only.** Fixes must follow the existing Domains structure and repository/service/controller separation. Business logic stays out of controllers and Blade. No hard-coded colors, use design tokens. No magic numbers, use config. Document new classes and methods with docblocks explaining why.
9. **Severity scale:**
   - **Critical**: exploitable now, leaks data across merchants, bypasses auth or payment, or allows remote code execution.
   - **High**: serious weakness needing a precondition, or a bug that loses money or data.
   - **Medium**: real defect with limited impact.
   - **Low**: hardening, polish, hygiene.
10. **No padding.** Do not list best practices that are already satisfied except in the short "Checked and fine" list.

---

## 2. Workflow (repeat per feature)

1. **Identify** the feature and choose its slug.
2. **Map it**: entry points (routes, UI), controllers, services, models, jobs, views, config, and tables.
3. **Trace the happy path** end to end, then the failure paths.
4. **Run the security checklist (section 3) against this feature.**
5. **Run the feature checks (section 4).**
6. **Write or update the feature file** using the template in section 5.
7. **Fix** what the rules allow, add tests, run them, and record each fix in the file.
8. **Update `docs/features/INDEX.md`** (status, severity counts, last reviewed).
9. Move to the next feature.

Do not stop between features unless you hit something that needs approval. At the end, produce the cross-cutting files in section 7.

---

## 3. Security checklist (apply to every feature)

Only record items that apply to the feature. Cross-cutting items (global config, headers, dependencies) go in `docs/features/_cross-cutting-security.md`, not in feature files.

**Tenant isolation (highest priority).** A merchant must never see or change another merchant's data.
- Every model the feature touches is scoped to the business/store (global scope, `BelongsToBusiness`, or equivalent).
- No bypasses: `withoutGlobalScopes()`, raw `DB::` queries, unscoped `find($id)`, route-model binding without scoping.
- Every route with an ID parameter verifies ownership (IDOR).
- Queued jobs and listeners carry and re-apply tenant context, since there is no request.
- Cache keys and storage paths are tenant-safe.

**Authentication and authorization.**
- Routes have auth middleware, admin routes have an admin-only guard.
- Policies/gates exist and controllers actually call `authorize()`.
- Staff vs owner separation: staff cannot reach billing, KYC, plan changes, or deletion.
- Mass assignment cannot set `role`, `is_admin`, `business_id`, `plan_id`, `kyc_status`, or similar.

**Input and output handling.**
- `$fillable`/`$guarded` are correct, no `$guarded = []`, no `update($request->all())`.
- Raw SQL uses bindings, never concatenated input.
- Blade `{!! !!}` usage is sanitized. Merchant and AI-generated content is escaped wherever rendered (public pages, emails, JSON in script tags).
- CSRF covers all state-changing routes, no unjustified exclusions.
- No open redirects. All input is validated.

**Uploads and storage.**
- Real MIME and extension validation, size and dimension limits, no executable or unsanitized SVG uploads.
- Sensitive files (KYC documents) are on a private disk and served through an authorized route, never a public URL.
- Random filenames, no path traversal.
- Limits are enforced **before** the file is written to disk.
- Orphaned files are cleaned up on deletion or failure.

**Payments and limits.**
- Webhooks verify signatures, are replay-safe, and idempotent.
- Amount, currency, and reference are verified server-side.
- A plan cannot be activated without verified payment.
- Money is stored as integers (kobo), no floats.
- Every plan limit (products, staff, AI credits, image caps) is enforced **server-side on every path**, not just hidden in the UI.

**AI-specific.**
- Prompt injection: merchant or customer content flowing into prompts, and what an injected instruction could cause.
- No other merchant's data can enter a prompt.
- AI output is untrusted: sanitized before rendering and schema-validated before saving.
- SSRF protection wherever the server fetches a user-supplied URL.
- Rate limits and credit checks on every AI path, including queued and bulk.
- Provider keys never reach the browser or logs. Prompts and images are not logged with personal data.

**Abuse and availability.**
- Rate limiting on login, registration, password reset, forms, public search, and AI endpoints.
- Bounded queries and pagination on public endpoints.
- Queue safety: retries, timeouts, failure handling, unbounded bulk operations.

---

## 4. Feature checks (apply to every feature)

- **Status**: Working, Partial, Stub (UI without backend or the reverse), Dead code, or Broken.
- **Journeys**: does the happy path work end to end? What breaks on failure?
- **States**: empty, loading, error, exhausted, and permission-denied states exist and read clearly.
- **Dead ends**: buttons or links that do nothing, routes with no UI, UI pointing at missing routes.
- **Leftovers**: TODO/FIXME/HACK comments, commented-out code, placeholder text.
- **Config and design**: hard-coded colors, fonts, strings, or limits that belong in tokens, theme settings, merchant settings, or config.
- **Data**: foreign keys and cascade behavior, missing unique constraints and indexes, transactions around multi-step writes, N+1 queries.
- **Performance**: unbounded queries, synchronous work that should be queued, image handling.
- **Tests**: what exists, what is missing, and whether it passes.
- **Documentation**: undocumented classes and methods on critical paths.

**Known issues to check in the relevant features:**
- AI drafts losing uploaded images: trace upload, staging, draft, review, and publish to find where the image reference is lost.
- Images not showing on products: verify the storage symlink, disk config, URL generation (`APP_URL`, `FILESYSTEM_DISK`), and stored vs served paths.
- Mismatch between plan slugs in the database and plan keys in config.
- Limits shown in the UI but not enforced on the server.

---

## 5. Feature file template

Copy this structure for every `docs/features/<feature-slug>.md`:

```
# <Feature name>

- Slug: <feature-slug>
- Status: Working | Partial | Stub | Dead code | Broken
- Last reviewed: <date>
- Findings: Critical X, High X, Medium X, Low X
- Fixed: X of Y

## 1. What it does
Two or three sentences in plain language, for a merchant-facing view and for a developer.

## 2. Map
- Routes / UI entry points:
- Controllers:
- Services / repositories:
- Models and tables:
- Jobs / events / listeners:
- Views / components:
- Config keys:
- Plan or limit gating:

## 3. How it works
Step-by-step flow, including failure paths. Note anything non-obvious.

## 4. Findings
For each finding:

### [SEVERITY] Short title
- Where: path/to/file.php:LINE (or route name)
- What: what is wrong
- Impact: what a user or attacker could do
- How to reproduce: steps or code path
- Fix: recommendation
- Resolution: Fixed | Proposed, needs approval | Won't fix (reason) | Unverified

## 5. Fixes applied
For each fix:
- Finding: <title it resolves>
- Change: what changed and why
- Files touched: list
- Tests added or run: names and result
- Notes: anything to watch after deploy (migrations, config:clear, worker restart)

## 6. Proposed, needs approval
Changes that fall under the "ask first" rule, with effort estimate (S/M/L).

## 7. Checked and fine
Short list of what was verified and found sound.

## 8. Unverified / needs manual check
Things you could not confirm from code alone.

## 9. Open questions
Decisions needed from the owner.
```

---

## 6. Suggested feature list (starting point)

Discover the real list from the code. Expect roughly these, each in its own file:

`merchant-onboarding-and-auth`, `business-types-store-and-service`, `products`, `services`, `categories`, `themes-and-theme-settings`, `public-store-pages`, `whatsapp-flow`, `marketplace-and-search`, `ai-product-creation-single-and-bulk`, `ai-photo-listing`, `ai-task-manager`, `ai-categories-and-descriptions`, `ai-credits-and-limits`, `billing-and-plans`, `kyc`, `admin-dashboard`, `staff-and-permissions`, `notifications-and-email`, `legal-and-static-pages`, `media-and-storage`.

If you find a feature that is not in this list, give it its own file. If a listed feature does not exist, say so in the INDEX instead of creating an empty file.

---

## 7. Index and cross-cutting files

After the per-feature work, create or update these in `docs/features/`:

1. **`INDEX.md`**: one table, one row per feature file.

   | Feature | File | Status | Crit | High | Med | Low | Fixed | Last reviewed |
   |---|---|---|---|---|---|---|---|---|

   Also list features that were expected but not found.
2. **`_cross-cutting-security.md`**: global items only: `APP_DEBUG` and debug tooling exposure, `.env` and secret leakage (including git history), `env()` used outside config, CORS, trusted proxies and hosts, HTTPS, security headers (CSP, frame-ancestors, nosniff, referrer policy, HSTS), session and cookie config, `composer audit` and `npm audit` results, and password reset and login throttling if not covered by a feature file.
3. **`_cross-cutting-data-and-ops.md`**: migration safety on a populated database, backups and restore, queue driver and worker config, scheduler, caching strategy, logging and alerting, production checklist (`APP_ENV`, config/route/view caching, storage symlink, permissions), hosting assumptions (shared hosting vs VPS), rollback plan.
4. **`_summary.md`**: one page. Overall health, the top 10 issues by severity across all features (link each to its feature file), and a recommended order of work: fix before launch, fix soon, later.
5. **`_changelog.md`**: every fix made, newest first: date, feature, one-line description, link to the feature file section. This is the quick way to see what the AI changed.

---

## 8. Finish

When done, reply with:
1. The list of feature files created.
2. Counts of findings and fixes by severity.
3. Everything under "Proposed, needs approval", across all features.
4. Deploy steps required by the fixes (migrations, config clear, queue worker restart).
5. Anything you were unsure about.
