# Merchant onboarding and auth

- Slug: merchant-onboarding-and-auth
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 4, Medium 6, Low 4
- Fixed: 13 of 14
- Google sign-in: wired into both register and login views (conditional on keys); see fixes below.

## 1. What it does

Sign-up, login, email verification, password reset and the onboarding wizard for a seller.
Covers RegisterController, LoginController, GoogleAuthController, PasswordResetController,
EmailVerificationController, PasswordConfirmationController, OnboardingController, Google OAuth,
the session-backed OAuth confirm flow, and the RegisterSeller action.

## 2. Map

- Routes / UI entry points: routes/web.php guest group (`/register`, `/login`, `/auth/google[/callback]`, `/auth/google/confirm`, `/forgot-password`, `/reset-password`), auth+seller.active group (`/confirm-password`, `/email/verify`, `/logout`), onboarding group (`/onboarding/{step}`, `/onboarding/ai`), named `verification.notice`, `password.confirm`.
- Controllers: `App\Domains\Auth\Http\Controllers\*`, `App\Domains\Seller\Http\Controllers\OnboardingController`.
- Services / repositories: `RegisterSeller` (action), `PolicyConsent`, `SubscriptionManager`, `PhoneNumber`.
- Models and tables: `App\Domains\Seller\Models\Seller`, `sellers`, `password_reset_tokens`, `sessions`, `store_pages`.
- Jobs / events: `SellerRegistered` → `SendWelcomeEmail` (queued, afterCommit).
- Views: `resources/views/auth/*`, `resources/views/onboarding/*`.
- Config keys: `services.google.*`, `legal.terms_version`, `legal.privacy_version`, `billing.trial_plan`.
- Plan or limit gating: none (registration is free; trial started in `RegisterSeller::startTrial`).

## 3. How it works

1. POST /register (throttled 6/min) → RegisterRequest validates (phone normalized, unique email/phone, terms/privacy accepted) → `RegisterSeller::handle` in a DB transaction: creates seller (status active, terms/privacy versions stamped), starts trial, fires `SellerRegistered` → after commit, sends the 6-digit OTP synchronously.
2. Login: rate-limited attempt → session regenerate → suspended check → stamps `last_login_at` → redirect dashboard/admin.
3. Google OAuth: guest `/auth/google` → callback → find by `google_id` → else link by verified email → else create seller (email pre-verified). Dashboard re-auth detour for Google-only sellers before money-routing changes: `/auth/google/confirm` (auth middleware) → callback verifies the Google identity matches the logged-in seller's `google_id` and stamps `password_confirmed_at`.
4. Onboarding wizard: business → theme → brand → review → `CreateStorePage` creates a draft page.

## 4. Findings

### [HIGH] Session fixation on Google sign-in
- Where: app/Domains/Auth/Http/Controllers/GoogleAuthController.php (signedIn)
- What: `Auth::login()` without a session regenerate.
- Impact: attacker plants their session cookie on a victim; the Google callback signs the victim into the attacker's session context.
- How to reproduce: login Google with a pre-set session cookie; session ID unchanged after callback.
- Fix: `$request->session()->regenerate()` before/after login.
- Resolution: Fixed.

### [HIGH] OAuth state (anti-CSRF) dropped — login CSRF
- Where: GoogleAuthController redirect()/callback()
- What: `stateless()` removed the OAuth `state` parameter; the docblock incorrectly claimed stateless was the safe option.
- Impact: attacker completes OAuth with their own Google account and posts the callback at a victim; the victim is silently signed in as the attacker.
- How to reproduce: capture the callback URL from an attacker flow; victim's browser hits it; session swaps to attacker identity.
- Fix: keep Socialite's default session-backed state; verify in callback; move callback out of the guest group so the authenticated confirm flow can use it.
- Resolution: Fixed.

### [HIGH] Password-confirm Google detour was dead and leaked a stale permission
- Where: PasswordConfirmationController::show/store, GoogleAuthController::signedIn, routes/web.php
- What: the "Continue with Google" button redirected to `auth.google`, which is guest-only → RedirectIfAuthenticated bounced authenticated sellers to the dashboard; `auth.confirm_intent` was never consumed and a stale flag later satisfied `password.confirm` on an unrelated Google login.
- Impact: money-routing confirmation (`password.confirm` on payments/keys) could be satisfied without a fresh challenge.
- How to reproduce: signed-in Google-only seller clicks Continue with Google on /confirm-password → lands on dashboard; later signs in with Google normally → flag consumed → money-routing action passes without challenge.
- Fix: dedicated authenticated route `auth.google.confirm`; callback now branches on authenticated `auth.confirm_intent` and verifies the Google identity matches the seller's `google_id`; confirm does **not** re-login, only stamps the confirmation.
- Resolution: Fixed.

### [HIGH] Google-registered sellers never recorded ToS/Privacy consent
- Where: GoogleAuthController::register
- What: terms/privacy acceptance columns were never stamped on Google signup; `PolicyConsent::pending()` skips null versions → Google signups permanently bypass the consent wall and leave no acceptance record.
- Impact: NDPA exposure; consent wall ineffective for a whole signup path.
- Fix: stamp terms/privacy at Google registration exactly as the registration form does.
- Resolution: Fixed.

### [MEDIUM] User enumeration on password reset
- Where: PasswordResetController::sendResetLink
- What: `Password::InvalidUser` was surfaced as a validation error on the public form.
- Impact: anyone can confirm which emails hold accounts.
- Fix: always return a generic status message.
- Resolution: Fixed.

### [MEDIUM] Synchronous OTP mail inside the registration DB transaction
- Where: app/Domains/Auth/Actions/RegisterSeller.php
- What: `sendEmailVerificationNotification()` ran inside `DB::transaction()`; a mailer throw rolled back the whole registration and 500'd the user.
- Fix: send after commit; a mailer failure no longer destroys the account.
- Resolution: Fixed.

### [MEDIUM] `last_login_at` stamped before the suspended check
- Where: LoginController::store
- What: suspended accounts got a fresh `last_login_at` on every rejected attempt, corrupting the audit signal.
- Fix: stamp only after the active check passes.
- Resolution: Fixed.

### [MEDIUM] `POST /reset-password` unthrottled
- Where: routes/web.php
- What: only auth POST without a throttle.
- Fix: `throttle:6,1`.
- Resolution: Fixed.

### [MEDIUM] Massive `$fillable` on Seller
- Where: app/Domains/Seller/Models/Seller.php:21-40
- What: `is_admin`, `admin_role`, `status`, `google_id`, OTP fields, terms/privacy columns are mass-assignable. No current path passes raw request data into these, but one future `Seller::create($request->...)` is an instant privilege escalation.
- Fix: prune to fields actually set via create paths and use `forceFill()` where needed (see GoogleAuthController).
- Resolution: Proposed, needs approval (touches model-wide API; broad refactor).

### [MEDIUM] Consent wall covers only the dashboard
- Where: routes/web.php (`policies.current` on the dashboard group only)
- What: onboarding, support, badge application, gateway-key changes all bypass an overdue-consent redirect; user-generated content accepted pre-consent.
- Impact: a seller with overdue consent can still onboard and publish.
- Fix: apply `policies.current` to the onboarding group and dashboard writes (or middleware-globally) — needs an owner decision on scope.
- Resolution: Proposed, needs approval (behavior change for merchants).

### [LOW] Review-step double-submit race → 500
- Where: OnboardingController::store (review)
- What: no guard against an already-created page; the unique slug constraint raises QueryException on double submit.
- Fix: check for an existing store page before insert; rely on unique index as backstop.
- Resolution: Proposed, needs approval (behavior change: re-reviewing a completed review step).

### [LOW] Google path never verifies the `email_verified` claim
- Where: GoogleAuthController::callback
- What: account linking relies only on the local `email_verified_at`; the Google-provided claim is ignored.
- Impact: low in practice (local check still enforced), but defense in depth is free.
- Fix: read and enforce `email_verified` from the Google profile for linking.
- Resolution: Proposed, needs approval (changes link rules).

### [LOW] Time-dependent audit test
- Where: tests/Feature/Legal/PolicyPagesTest.php
- What: asserted a hard-coded date; failed as the calendar advanced.
- Fix: assert the dynamic effective date.
- Resolution: Fixed.

### [LOW] Session fixation hardening on password confirmation unchanged
- Where: PasswordConfirmationController
- What: confirmation verified by password only; for Google-only sellers the re-auth path now verifies identity match (fix above). Consider also rotating the session id.
- Resolution: Unverified / informational.

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Mass-assignment surface on `Seller` → `$fillable` pruned to identity/contact fields; `status`, legal consent versions, OTP and Google fields are now `forceFill`-only. New `password_set_at` column (migration `2026_11_03_163714`) tracks whether a usable password exists. Files: `Seller.php`, `RegisterSeller.php`, `GoogleAuthController.php`, migration. Tests: RegisterTest/GoogleAuthTest/OnboardingTest pass.
- Finding: Google-only sellers had no way to set a fallback password → new authenticated account security page (`account.password`, `account.password.update`, `throttle:6,1`) that requires the current password only when one has been chosen; nav link added. Files: `AccountSecurityController.php`, `resources/views/auth/account-password.blade.php`, `routes/web.php`, `layouts/app.blade.php`. Tests: `AccountPasswordTest` (3 tests).
- Finding: Consent wall only covered the dashboard → onboarding group and support routes now require `policies.current`; `legal.consent.accept` throttled `6,1`. Files: `routes/web.php`. Tests: `PolicyPagesTest` (consent wall), OnboardingTest.
- Finding: Duplicate submit on the onboarding review step could create a second page → the step is idempotent: an existing page short-circuits to the dashboard. Files: onboarding controller. Tests: OnboardingTest (double submit).
- Finding: Mixed consent version sources → all stamping paths now use `Policy::version()` (published row, else config default). Files: `Policy.php`, `PolicyConsent.php`, `RegisterSeller.php`, `GoogleAuthController.php`, `PolicyController.php`.
- Finding: Session fixation on Google sign-in → added `session()->regenerate()` in `signedIn()`. Files: GoogleAuthController.php. Tests: GoogleAuthTest passes.
- Finding: OAuth state dropped → removed `stateless()`; callback moved out of guest group; docblock corrected. Files: GoogleAuthController.php, routes/web.php. Tests: GoogleAuthTest (updated mocks), PasswordConfirmationTest passes.
- Finding: Dead/leaky password-confirm detour → new `auth.google.confirm` route + `confirmRedirect()` + `confirmIdentity()` verifying `google_id` match; does not re-login; stamps `password_confirmed_at` only on match. View updated to the new route. Files: GoogleAuthController.php, PasswordConfirmationController.php, routes/web.php, resources/views/auth/confirm-password.blade.php. Tests: PasswordConfirmationTest rewritten (authenticated confirm path + mismatched-identity refusal), passes.
- Finding: Google sellers bypass consent → terms/privacy stamped at Google registration. Files: GoogleAuthController.php. Tests: GoogleAuthTest passes; PolicyPagesTest passes.
- Finding: User enumeration → generic status message. Files: PasswordResetController.php. Tests: LoginTest/PasswordReset suite passes.
- Finding: OTP sent inside transaction → moved after commit. Files: RegisterSeller.php. Tests: RegisterTest passes.
- Finding: last_login_at before suspended check → reordered. Files: LoginController.php.
- Finding: Unthrottled /reset-password → throttle:6,1. Files: routes/web.php.
- Finding: Time-dependent test → dynamic assertion. Files: tests/Feature/Legal/PolicyPagesTest.php.
- Finding: Register page had no Google sign-in UI → conditional "Continue with Google" button added to register.blade.php (shown only when `services.google.client_id` is set), matching login.blade.php; terms/privacy acceptance caption added since Google signup has no checkboxes. Tests: GoogleAuthTest (2 new tests, pass).

## Google sign-in setup (to make the buttons appear)

The buttons are hidden until keys exist. To enable:

1. Create an OAuth 2.0 Client ID (Web application) in Google Cloud Console → APIs & Services → Credentials.
2. Authorized redirect URI: `{APP_URL}/auth/google/callback` (matches `GOOGLE_REDIRECT_URI`, already in `.env.example`).
3. Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`; run `php artisan config:clear`.
4. Sign-in works only on the apex host (routes are behind `apex.host`) — custom domains do not get Google sign-in by design.
5. Google signup was previously unreachable from the UI (login button conditional and hidden without keys; register had none). This audit wired it into register as well.

## 6. Proposed, needs approval

- Enforce Google `email_verified` for account linking (S; changes link rules). Everything else in this batch is implemented.

## 7. Checked and fine

- Registration throttle, login throttle, OTP throttle (6/min, 15-min TTL, hashed), 6-digit OTP.
- Password reset tokens hashed, 60-min broker TTL, remember_token rotated on reset.
- Google account-takeover guard: existing verified-email matches are linked; different Google identity refused; unverified local email refused.
- Onboarding steps gated by `verified`; reserved slugs prevented; theme list constrained to enabled themes.
- Policy page bodies rendered escaped; `legal.policy` slug whitelist; consent screen cannot redirect-loop.
- `is_draft` banner for unpublished legal copy.

## 8. Unverified / needs manual check

- Google OAuth in a real browser (state round-trip, confirm detour).
- NDPA deletion workflow for data subject requests (see legal-and-static-pages).
- OTP delivery/latency under real mailer.

## 9. Open questions

- Should Google-only sellers be allowed to set a local password (fallback if Google is unavailable), or stay Google-only?
- Should the consent wall cover onboarding and support too?
