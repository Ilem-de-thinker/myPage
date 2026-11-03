# Mercha Audit Playbook

A full audit of the Mercha Laravel codebase: security, feature completeness, data integrity, AI features, billing, performance, code quality, and deploy readiness.

## How to use this file

Give this file to your coding AI with the instruction:

> Run the audit in MERCHA_AUDIT.md. Read-only. Do not change any code. Produce the reports described in the final section.

---

## Ground rules for the auditor

1. **Read-only.** Do not modify code, migrations, config, or data during the audit. Only write the report files.
2. **Evidence or it did not happen.** Every finding needs a file path and line range (or route name), a short explanation of why it is a problem, and how it could be triggered. Do not report a vulnerability you have not traced through the actual code path.
3. **No guessing.** If you cannot verify something, put it under "Unverified / needs manual check", not under findings.
4. **No padding.** Do not list generic best practices that are already satisfied. Only report real gaps, plus a short "checked and fine" list so I know what was covered.
5. **Severity scale:**
   - **Critical**: exploitable now, leaks data across merchants, bypasses auth or payment, or allows remote code execution.
   - **High**: serious weakness needing some precondition, or a feature that is broken in a way that loses money or data.
   - **Medium**: real weakness or defect with limited impact.
   - **Low**: hardening, polish, or hygiene.
6. **Laravel only.** Recommend fixes in Laravel terms. Do not suggest switching frameworks or backends.
7. **Respect the architecture.** Fixes should keep the existing Domains structure and the repository/service/controller separation.

---

## Part 1: Security check

### 1.1 Tenant isolation (highest priority)

Mercha is multi-tenant. A merchant must never see or change another merchant's data.

- [ ] List every model that belongs to a business/store and confirm each is scoped (global scope, `BelongsToBusiness` trait, or equivalent).
- [ ] Find every query that bypasses scoping: `withoutGlobalScopes()`, raw `DB::` queries, `DB::table()`, `whereRaw`, `Model::find($id)` on unscoped models, route-model binding without scoping.
- [ ] For every route with an ID parameter (product, service, order, draft, AI task, invoice, KYC record, upload), confirm the controller verifies ownership. Look for IDOR.
- [ ] Check queued jobs and event listeners: does the job carry the tenant context, and does it re-scope when it runs? Jobs run without a request, so request-based tenant resolution may not apply.
- [ ] Check cached data keys: are cache keys tenant-prefixed?
- [ ] Check file storage paths: can one merchant guess or access another merchant's files?

### 1.2 Authentication and authorization

- [ ] Review all route groups in `routes/*.php`. List every route without auth middleware and justify each one.
- [ ] Confirm admin routes are protected by an admin-only guard or role check, not just by being "hidden".
- [ ] Check for policies/gates: which models have policies, which controllers actually call `authorize()`, and which skip it.
- [ ] Role and permission checks for merchant staff vs owner. Can staff reach owner-only actions (billing, KYC, plan changes, deletion)?
- [ ] Password reset, email verification, login throttling, and registration abuse protection.
- [ ] Session config: secure cookies, same-site, session lifetime, session fixation on login.
- [ ] Check for privilege escalation through mass assignment (e.g. `role`, `is_admin`, `business_id`, `plan_id`, `kyc_status`).

### 1.3 Input handling and injection

- [ ] Mass assignment: every model's `$fillable`/`$guarded`. Flag any `$guarded = []` and any `->update($request->all())` or `->fill($request->all())`.
- [ ] Raw SQL: search for `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`, `DB::statement`. Confirm bindings are used and no user input is concatenated.
- [ ] XSS: search Blade for `{!! !!}` and confirm each is sanitized. Check merchant-supplied content (product names, descriptions, page content, AI-generated text) wherever it is rendered, including public store pages, emails, and JSON embedded in script tags.
- [ ] Check merchant-controlled URLs and custom HTML/CSS fields (theme settings, brand colors, custom CSS) for injection.
- [ ] CSRF: confirm all state-changing routes are protected. List any CSRF exclusions and justify them.
- [ ] Open redirects: any `redirect($request->input(...))` or `redirect()->to($url)` using user input.
- [ ] Validation: controllers that use `$request->input()` directly without validation.

### 1.4 File uploads and storage

- [ ] Validate file type by real MIME and extension, not just client-supplied type. Check max size, image dimensions, and pixel-flood protection.
- [ ] Confirm uploaded files cannot be executed (no PHP in a public directory, no `.php` or `.phtml` accepted, SVG sanitized or disallowed).
- [ ] Storage visibility: which disks are public, which are private? **KYC documents and other sensitive files must be on a private disk and served through an authorized route, never via a public URL.**
- [ ] Check filenames: random/hashed names, no path traversal (`../`) from user-provided names.
- [ ] Check the public storage symlink setup and whether files that should be public (product images) are reliably reachable via their stored URL.
- [ ] Orphaned files: are files removed when records are deleted? Are rejected or failed uploads cleaned up?
- [ ] Confirm that an upload limit is enforced **before** the file is written to disk.

### 1.5 Secrets, config, and environment

- [ ] Confirm `.env` is not committed. Search git history for leaked secrets (API keys, Paystack keys, AI provider keys, DB passwords).
- [ ] `APP_DEBUG` must be false in production. Check any debug routes, `dd()`, `dump()`, `phpinfo()`, Telescope/Debugbar/Ignition exposure.
- [ ] Check that secrets are only read through `config()`, never `env()` outside config files (breaks config caching and can leak).
- [ ] Check `APP_KEY` handling and encryption of sensitive columns (API tokens, third-party credentials).
- [ ] Check CORS config, trusted proxies, trusted hosts, and forced HTTPS in production.

### 1.6 Payments, billing, and webhooks

- [ ] Webhook endpoints (Paystack or other): signature verification, replay protection, idempotency (the same event processed twice must not double-credit or double-activate).
- [ ] Payment verification: is the amount, currency, and reference verified server-side, or does the app trust a client callback?
- [ ] Can a merchant activate or upgrade a plan without a verified payment?
- [ ] Money handling: stored as integers (kobo), no floating-point arithmetic on money.
- [ ] Plan limits (max products, staff, AI credits, image caps): are they enforced **server-side** on every path, or only hidden in the UI?

### 1.7 AI-specific security

- [ ] Prompt injection: merchant or customer content (product text, uploaded images, scraped URLs) flowing into prompts. What can an injected instruction cause: data exfiltration, tool calls, cross-tenant data in the prompt?
- [ ] Make sure no other merchant's data can enter a prompt.
- [ ] AI output is untrusted: is it sanitized before rendering as HTML, and validated against a schema before being saved?
- [ ] SSRF: any feature where the server fetches a user-supplied URL (image URL, website import). Check for internal IP/metadata endpoint blocking.
- [ ] Cost abuse: rate limits and credit checks on every AI endpoint, including queued and bulk paths. Can someone bypass limits by calling endpoints directly or in parallel?
- [ ] Provider API keys: server-side only, never exposed to the browser or logs.
- [ ] Logging: are prompts, images, or personal data logged in a way that leaks sensitive information?

### 1.8 Rate limiting, abuse, and availability

- [ ] Rate limiters on login, registration, password reset, contact forms, public search, AI endpoints, and any public write endpoint.
- [ ] Public marketplace/search endpoints: pagination limits, query cost, scraping protection.
- [ ] Queue and job safety: retries, timeouts, failure handling, poison-job behavior, unbounded bulk operations.

### 1.9 Dependencies and headers

- [ ] Run `composer audit` and `npm audit`. List vulnerable packages, and whether the vulnerable code path is actually used.
- [ ] Security headers: CSP, X-Frame-Options/frame-ancestors, X-Content-Type-Options, Referrer-Policy, HSTS.
- [ ] Remove or flag abandoned or unnecessary packages.

### 1.10 Privacy and data handling

- [ ] Personal data inventory: what is stored (names, phones, addresses, KYC documents, customer contacts)?
- [ ] Are sensitive fields masked in logs, admin UI, and error pages?
- [ ] Account deletion and data export: does deleting a merchant remove files and related records?
- [ ] Do terms, privacy, and refund pages exist and match what the system actually does?

---

## Part 2: Features audit

The goal is a truthful map of what exists, what works, and what is half-built.

### 2.1 Feature inventory

Build a table of every feature, derived from routes, controllers, views, jobs, and config (not from assumptions):

| Feature | Entry point (route/UI) | Backend (service/model) | Status | Notes |
|---|---|---|---|---|

Status values: **Working**, **Partial** (exists but incomplete or buggy), **Stub** (UI without backend, or backend without UI), **Dead code** (unreachable), **Broken**.

Cover at least: merchant onboarding, store and service business types, product and service CRUD, categories, themes and theme settings, public pages, WhatsApp links and flows, marketplace/directory and search, AI creation flows (single, bulk, from image), AI task manager and floating indicator, AI category generation and descriptions, billing and plans, KYC, admin dashboard, staff management, notifications and emails, legal and static pages.

### 2.2 Trace critical user journeys end to end

For each journey, walk through the code and note anything that would fail:

1. A new merchant signs up, picks a business type, and publishes a first page.
2. A merchant creates a product manually, with images.
3. A merchant creates products with AI (single, bulk, from uploaded photos), reviews the draft, and publishes.
4. A merchant hits a plan limit (products, staff, AI credits, image cap) and is prompted to upgrade.
5. A merchant upgrades or pays and the plan is activated.
6. A customer finds a store, views a product, and contacts the merchant via WhatsApp.
7. A merchant completes KYC and an admin reviews it.
8. An admin manages merchants, plans, and settings.

### 2.3 Known issues to check specifically

- [ ] **AI drafts losing uploaded images**: images uploaded in the AI flow must reliably attach to the created product or service. Trace upload, staging, draft, review, and publish, and find where the image reference is lost.
- [ ] **Images not showing on products**: verify the storage symlink, disk configuration, URL generation (`APP_URL`, `FILESYSTEM_DISK`), and whether stored paths match served paths.
- [ ] Mismatches between plan slugs in the database and plan keys in config.
- [ ] Any place where a limit is displayed in the UI but not enforced on the server.

### 2.4 Completeness and consistency

- [ ] Routes that exist with no UI link, and UI links pointing to missing routes.
- [ ] Buttons, forms, and menu items that do nothing.
- [ ] TODO/FIXME/HACK comments, commented-out code, and placeholder text left in the UI.
- [ ] Empty, loading, error, and exhausted states for every major screen.
- [ ] Validation messages: clear and consistent. Success and failure feedback after actions.
- [ ] Hard-coded colors, fonts, or strings that should come from design tokens, theme settings, or merchant settings.

### 2.5 Missing or recommended features

Separate clearly from defects. For each, give a one-line reason and an effort estimate (S/M/L). Focus on what blocks launch or revenue first.

---

## Part 3: AI features audit

- [ ] List every AI entry point (controller, job, service) and the model/provider it calls.
- [ ] How credits are counted, when, and whether the count can be bypassed or double-counted. Check race conditions in bulk and queued flows.
- [ ] Failure handling: provider timeouts, rate limits, malformed output. Is the merchant told, and is usage released on failure?
- [ ] Output validation: structured output schemas, price and currency handling, category matching, duplicate detection.
- [ ] Prompt quality and cost: prompt size, image size sent to the provider, redundant calls, caching opportunities.
- [ ] The AI task manager: job status accuracy, stuck jobs, cancellation, and what happens when a worker restarts mid-task.
- [ ] Observability: can you tell from logs why an AI task failed?

---

## Part 4: Data integrity and database

- [ ] Foreign keys and cascade rules. What happens to products, images, and drafts when a store, category, or user is deleted?
- [ ] Missing unique constraints (slugs, per-tenant uniqueness, payment references).
- [ ] Missing indexes on columns used in `where`, `orderBy`, and joins, especially on tenant ID, slug, status, and created_at.
- [ ] Transactions around multi-step writes (payment activation, bulk creation, draft publishing).
- [ ] N+1 queries on list pages, the marketplace, and the dashboard. Eager loading gaps.
- [ ] Migration safety: can they run on a populated production database? Any destructive or long-locking migrations?

---

## Part 5: Performance and reliability

- [ ] Slowest pages and endpoints, by code inspection: unbounded queries, missing pagination, heavy loops, synchronous work that should be queued.
- [ ] Image handling: resizing, size limits, and serving large originals to the public.
- [ ] Caching: what is cached, how it is invalidated, and tenant safety.
- [ ] Queues: driver, worker configuration, retry and backoff, failed-job handling, and supervisor/restart procedure.
- [ ] Scheduled tasks: defined, documented, and idempotent.
- [ ] Error handling and logging: log levels, no silent catches, and alerting readiness.

---

## Part 6: Code quality and architecture

- [ ] Adherence to the Domains structure and repository/service/controller separation. List the worst offenders (fat controllers, logic in Blade, models doing too much).
- [ ] Duplicated logic that should be one service.
- [ ] Magic numbers and hard-coded limits that belong in config.
- [ ] Documentation: undocumented classes and methods in critical paths.
- [ ] Test coverage: what exists, what is missing for critical paths (billing, limits, tenancy, AI credits, uploads). Run the test suite and report failures.
- [ ] Static analysis: run Pint/PHPStan/Larastan if configured, and report the results.

---

## Part 7: Deploy and operations readiness

- [ ] Production checklist: `APP_ENV`, `APP_DEBUG`, config/route/view caching, queue worker running, scheduler running, storage symlink, file permissions.
- [ ] Backups: database and uploaded files. Is there a restore procedure?
- [ ] Environment variables: a complete documented list with which are required.
- [ ] Hosting assumptions: anything that will break on shared hosting vs a VPS (symlinks, long-running workers, cron).
- [ ] Monitoring: error tracking, uptime, queue depth.
- [ ] Rollback plan for a bad deploy and for a bad migration.

---

## Deliverables

Write these files to `docs/audit/` (create the folder). Do not change anything else.

1. **`00-summary.md`**: one page. Overall health, the top 10 issues by severity, and a recommended order of work (what to fix before launch, what can wait).
2. **`01-security.md`**: findings grouped by section 1.1 to 1.10. Each finding uses this format:

   ```
   ### [SEVERITY] Short title
   - Where: path/to/file.php:LINE (or route name)
   - What: what is wrong
   - Impact: what an attacker or user could do
   - How to reproduce: steps or code path
   - Fix: concrete recommendation (Laravel-style)
   ```

   End with "Checked and fine" and "Unverified / needs manual check".
3. **`02-features.md`**: the feature inventory table, the journey walkthroughs with failure points, the known-issues results, and recommended features.
4. **`03-ai.md`**: AI audit results.
5. **`04-data-and-performance.md`**: sections 4 and 5.
6. **`05-code-quality-and-deploy.md`**: sections 6 and 7.
7. **`06-fix-plan.md`**: a prioritized, phased fix plan. Group by "Fix before launch", "Fix soon", and "Later". Each item should reference its finding, give an effort estimate (S/M/L), and note dependencies between fixes.

After the audit, stop and wait for my approval before changing any code.
