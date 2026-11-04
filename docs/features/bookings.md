# Bookings

- Slug: bookings
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 2, High 3, Medium 5, Low 4
- Fixed: 12 of 14

Includes the WhatsApp booking flow (`POST /book/whatsapp` → prefilled `wa.me` link) and the
public booking/estimate screens. The seller-facing inbox and service CRUD live here too.

## 1. What it does

Customers book services (free or with a deposit); sellers manage the inbox, staff, hours and
time-off. Deposits flow through the same gateway flow as orders. Includes the public booking
widget, availability endpoint, WhatsApp hand-off, and manage/reschedule by reference.

## 2. Map

- Routes: dashboard `bookings.*`, `services.*`, `availability.*`, `staff.*`, `time-off.*` (routes/web.php:266-301); public `storefront.book*`, `storefront.availability`, `storefront.manage-booking`, `storefront.booking.*` (routes/storefront.php).
- Controllers: `PublicBookingController`, `BookingController`, `ServiceController`, `StaffMemberController`, `WorkingHourController`, `TimeOffController`, `RescheduleBookingController` (dashboard), `StorefrontController::manageBooking/rescheduleBooking` (public).
- Actions: `CreateBooking`, `RescheduleBooking`, `UpdateBookingStatus`, `ExpirePendingBookings`, `RequestBookingByWhatsApp`, `UpdateWorkingHours`.
- Models and tables: `Service`, `Booking`, `StaffMember`, `WorkingHour`, `TimeOff` (no seller scope by design — controller checks), `BookingStatusHistory`.
- Jobs: `ExpirePendingBookings` (every minute), `SendBookingReminders`.

## 3. How it works

Free bookings confirm immediately. Deposits create a `pending_payment` booking + gateway
transaction; the buyer pays; confirmation must flip the booking to `confirmed` (currently
broken — see D1). The dashboard inbox lists today/upcoming/past; rescheduling re-checks
availability under the same store-page lock; time-off removes slots; expiry sweeps stale holds.

## 4. Findings

### [CRITICAL] Paid bookings never settle
- Where: `PublicBookingController::store` (deposit path), `SettleOrderAfterPayment` (ignores non-Order payables), `CheckoutController::callback`, `ExpirePendingBookings`
- What: buyer pays deposit → `PaymentConfirmed` fires → no listener confirms the Booking; the browser callback finds no Order and bounces to the home page; the booking expires at +15 min despite being paid.
- Impact: buyer charged, loses the slot, seller unaware.
- Fix: new `SettleBookingAfterPayment` listener on `PaymentConfirmed` (payable = Booking → confirm + notify), registered like `SettleOrderAfterPayment`.
- Resolution: Fixed.

### [CRITICAL] Stored XSS via `Html::sanitize()` keeping dangerous attributes
- Where: app/Shared/Support/Html.php (before fix) — used by product descriptions rendered raw on storefronts
- What: tags allowlisted but attributes untouched → `<img src=x onerror=...>` executes in every buyer's browser.
- Fix: rewrote the sanitizer to a full tree-walk with tag/attribute allowlists and URL scheme checks; unit-tested (9 tests). See _cross-cutting-security.md.
- Resolution: Fixed.

### [HIGH] No rate limiting on public booking creation
- Where: routes/storefront.php — `POST /book` (no throttle), and unthrottled manage-booking GETs
- What: each paid-path POST creates a pending gateway transaction; manage endpoints allow unlimited reference/contact probing.
- Fix: `throttle:10,1` on `POST /book`, `throttle:30,1` on both manage GETs.
- Resolution: Fixed.

### [HIGH] Public endpoints 500 on garbage dates
- Where: PublicBookingController `availability()`, `store()`, `requestWhatsApp()`; RescheduleBookingController::store; TimeOffController::store
- What: `Carbon::parse()` on unvalidated input → `InvalidFormatException`/`DateMalformedStringException` → 500.
- Fix: `date`/`date_format` validation + guarded parsing throwing `ValidationException` (422); `start_time`/`end_time` validated as `H:i`.
- Resolution: Fixed.

### [HIGH] Unhandled InvalidTransition → 500 on the dashboard
- Where: BookingController::updateStatus, BookingController::cancel
- What: terminal-state moves throw `InvalidTransition`; orders pre-check with `canMoveTo`, bookings did not.
- Fix: caught and converted to 422 validation errors; regression tests added.
- Resolution: Fixed.

### [MEDIUM] Dashboard reschedule silently drops the chosen staff member
- Where: RescheduleBookingController::store — validated `staff` but never passed it to the action
- Fix: resolve staff tenant-scoped, pass to `RescheduleBooking::handle()` (same semantics as the public path). Regression test added.
- Resolution: Fixed.

### [MEDIUM] Cancel note unvalidated → possible 500 on strict SQL
- Where: BookingController::cancel
- Fix: `note` validated `nullable|string|max:255` (column is `string(255)`).
- Resolution: Fixed.

### [MEDIUM] Booking confirmation page discloses name+phone on reference alone
- Where: PublicBookingController::confirmation (reference = sole credential)
- Fix: same treatment as manage flow (reference + contact), or a short-lived signed URL.
- Resolution: Fixed.

### [MEDIUM] Time-off model unscoped by design; relies on controller checks
- Where: `TimeOff` has no `BelongsToSeller`/seller_id
- Fix: leave as designed; ownership is enforced in controllers (verified). Document.

### [LOW] Availability N+1 (`isBookable` per slot)
- Where: AvailabilityService::slotsFor → isBookable → 2 queries per slot
- Fix: batch-load bookings/time-off for the day once, compute in memory.
- Resolution: Fixed — `slotsFor()` reads working hours, bookings and time off once (week-padded window) and `isBookable()` checks those collections in memory. Files: `AvailabilityService.php`. Tests: `AvailabilityServiceTest` (3 queries for 15 slots).

### [LOW] `expires_at` sweep query unindexed; `(status, expires_at)` index missing
- Fix: add composite index via new migration.
- Resolution: Fixed.

### [LOW] Unassigned holds do not block staffed slots (design)
- Documented behavior of `scopeScopedTo(null)`; a 1-person shop can double-book.
- Resolution: Won't fix (design); noted.

### [LOW] Deposit naira→kobo truncates (`(int)($v*100)`)
- Fix: route through `Money::toKobo` for consistency with product prices.
- Resolution: Fixed.

### [LOW] Booking notifications render times in the wrong zone
- Where: BookingStatusChangedNotification reads non-existent `->storePage->timezone` attribute
- Fix: read `settings['timezone']` like BookingReminder does.
- Resolution: Fixed.

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Paid bookings never settled → `SettleBookingAfterPayment` is registered and settles the booking on payment success. Tests: `PaidBookingSettlementTest`.
- Finding: Confirmation page disclosed name+phone on reference alone → `ContactMatcher` also requires a matching contact before revealing details; throttles remain.
- Finding: `expires_at` sweep unindexed → composite `(status, expires_at)` index added (migration `2026_11_03_164704_add_status_expiry_index_to_bookings.php`).
- Public route throttles (10/min create, 30/min manage) — routes/storefront.php.
- Date/time validation + guarded Carbon parsing (422 not 500) — PublicBookingController, TimeOffController, RescheduleBookingController; covered by existing tests + import of the 422 path in lifecycle tests.
- `InvalidTransition` → 422; regression tests `BookingLifecycleTest` (illegal status move, cancel from terminal).
- Reschedule staff pass-through with tenant-scoped resolution; regression test asserts assigned staff.
- Cancel note validation (`max:255`).
- `Html::sanitize` full rewrite with tests (see `_cross-cutting-security.md`) — kills the stored-XSS vector used for storefront product/service descriptions.

Deploy notes: new throttles take effect on route cache rebuild; no migrations.

## 6. Proposed, needs approval

- Informational/design items: time-off scoping; unassigned holds.

## 7. Checked and fine

- Tenant checks on dashboard screens (404 for foreign pages), public manage-lookup secrecy, double-booking lock in same-staff path, WhatsApp flow creates no slot hold (by design), seller confirmation of WhatsApp requests via state machine, time-off delete path preserved, inbox pagination, reminder idempotency.

## 8. Unverified / needs manual check

- Live Paystack first-payment booking flow end to end (blocked on D1 decision).
- GD timezone correctness in notifications.

## 9. Open questions

- Should a buyer who pays a deposit get instant confirmation, or does the seller always confirm manually regardless of payment?
- Should the confirmation page keep working on reference alone (receipt use case) or always require contact?
