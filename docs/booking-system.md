# The Booking system (Service theme)

How the service side of mypage.ng works, and the rules that are easy to break by accident.

---

## The one thing to know first

**A service seller must set opening hours before anyone can book.** `/availability` is the only
screen that writes `working_hours`, and `AvailabilityService` reads nothing else. If the weekly
grid is empty, the public booking widget renders correctly and returns zero slots — it is not an
error, it is simply nothing to book. PublishReadiness does not check this yet, which is a known
rough edge (see *Known gaps* below).

---

## What a seller does, in order

1. **Services** (`/services`) — name, duration, reset time, price, deposit.
2. **Team** (`/staff`) — optional. Without staff, every booking goes to whoever is free.
3. **Availability** (`/availability`) — weekly grid of opening hours. **Store-wide hours gate
   the whole booking flow.** Per-staff hours, set from the same screen, override them.
4. **Dates off** (`/availability/time-off`) — holidays and closures, whole shop or one person.
5. **Publish** — the readiness checklist requires a description, a WhatsApp number, at least one
   enabled section, and at least one active service.

A customer then books at `/{slug}/book`.

---

## Scheduling rules

### Timezones

Everything is reasoned in the **store's timezone** (`store_pages.settings.timezone`, default
`Africa/Lagos`) and converted to UTC only at the query edges. A salon open at 09:00 in Lagos
opens at 09:00 in Lagos on whatever day the slot list is asked for. Changing `app.timezone` must
not change what a Lagos seller sees.

### Who is free

The meaning of "free" depends on whether a person was chosen:

| Query | Means | Counts |
|---|---|---|
| `slotsFor($service, $date, $staff)` | that person | their bookings, their time off, their hours |
| `slotsFor($service, $date, null)` | the shop | **shop-wide** bookings and time off only |

That distinction is why one busy stylist does not close the salon, and why one person's holiday
does not either. It lives in `Booking::scopeScopedTo` and `TimeOff::scopeScopedTo`.

A named person is governed by **their own hours if they have any, else the shop's**.

### Overlap

Intervals are **half-open**: a booking ending exactly when the next starts is fine. A slot is
blocked if `[start - buffer, end + buffer]` intersects an occupied window — the buffer is the
seller's reset time, so it must fit on both sides.

### The slot grid

A day's slots step by `mypage.booking_slot_step_minutes` (30), and the last bookable start is the
latest one where `duration + buffer` still fits before closing.

### Notice window

Nothing inside `mypage.booking_min_notice_minutes` (60) is offered. A slot starting in ten
minutes is not a booking, it is a queue.

---

## Holding a slot

`CreateBooking` is the only thing that reserves one:

1. Locks the **store page row** (`lockForUpdate`).
2. Re-runs the availability check **under that lock** — never trusting the slot list the
   visitor's page rendered minutes ago.
3. Creates the booking.

That lock is why two people cannot take one slot. `RescheduleBooking` uses the same helper
(`StorePageForBooking::lock`), so a reschedule and a new booking cannot pass each other.

**What gets held depends on what is owed:**

- Free service (`price_kobo = 0`) → `confirmed` immediately, no expiry, no hold.
- Anything else → `pending_payment` with `expires_at` now + `mypage.booking_hold_minutes`.
  `ExpirePendingBookings` runs **every minute** to release lapsed holds. A fifteen-minute hold
  cannot wait for an hourly sweep.

A **WhatsApp request holds nothing** (`expires_at` is null). The buyer asks and the seller
agrees. That is deliberate: silently reserving a slot for someone who may never pay would lock
paying customers out of it.

---

## Money

`Service::amountDueKobo()` is what the buyer owes: the deposit if there is one, otherwise the
whole price. `Service::depositKobo()` is the deposit alone. The distinction matters — "no deposit"
means *pay in full*, not *pay nothing*.

Everything is integer kobo. The seller types naira; the form multiplies by 100 once.

`Booking::amountDueKobo()` mirrors the service so the two can never disagree.

---

## Changing a booking

- **Cancel** — allowed at any point the lifecycle permits. Frees the slot.
- **Reschedule** — refused inside the seller's cancellation window
  (`store_pages.settings.cancellation_window_hours`, default 24). The screen says so and shows
  the customer's number, because the honest answer is "ring them", not a dead button.

Status only ever changes through `UpdateBookingStatus`, which validates the move against
`BookingStatus::allowedTransitions()` and writes a `booking_status_histories` row.

---

## Lifecycle

```
pending_payment → confirmed → completed → (terminal)
       ↓              ↓
  cancelled      cancelled / no_show
       ↓
   expired
```

`countsTowardsLifetimeValue()` is true only for `confirmed` and `completed`, and that drives the
customer's lifetime value through `BookingStatusChanged` — so a cancelled or no-show appointment
does not inflate it.

`notifiesBuyer()` is deliberately narrower: confirmed, completed, cancelled, no-show. Nobody needs
an email saying a booking moved to "processing".

---

## Reminders

`SendBookingReminders` runs every fifteen minutes with a thirty-minute window **centred on the
lead time**, not on `now()` — a booking exactly 24 hours out is the one the 24h reminder is for.
The two lead times are stamped separately (`reminded_24h_at`, `reminded_2h_at`) so neither can
send twice, and each is stamped *before* sending so a mail failure cannot become a retry storm.

Each reminder carries a `whatsappLink()` the seller can forward by hand until the Cloud API ships.

---

## Where the buyer lives

Bookings and orders write into **one customer book** through `CustomerIdentity`. A phone number is
the strongest identity, an email the fallback, and a buyer who later supplies a phone has their
record upgraded rather than duplicated. `customers.purchaseCount()` picks `booking_count` or
`order_count` depending on the seller's theme, so the list never reads "0 orders" next to a full
booking history.

---

## Gotchas that have already bitten us

- **`?->` is fine inside `"{$obj->rel?->prop}"`, but `?:` is a parse error.** Pull optional and
  defaulted values into a variable before interpolating them.
- **A public method on an Eloquent model is treated as a relationship** when accessed as a
  property. `$customer->purchaseCount` throws; `$customer->purchaseCount()` is fine.
- **Route parameters bind by name.** A `{staff}` route will not populate a `$staffMember`
  argument, and an optional model cannot be bound from a *query string* — it needs a real path
  segment (`availability/{staff?}`).
- **Do not open an `@if` inside a Blade component slot.** It compiles to a stray `endif`.
- **Register literal routes before parameterised ones** (`availability/time-off` before
  `availability/{staff?}`).
- **Never promote `$message` as readonly on an exception.** `Exception` already declares it, and
  redeclaring is a compile-time fatal that kills the process with no output at all.

---

## Tests

| File | Covers |
|---|---|
| `AvailabilityServiceTest` | the engine: timezones, buffers, half-open overlap, buffers, weekday boundaries, per-staff hours, notice window |
| `AvailabilitySettingsTest` | **drives availability only through the dashboard** — this is the file that catches "the engine works but a seller cannot reach it" |
| `CreateBookingTest` | deposits, double-booking, cross-seller isolation, hold expiry |
| `BookingLifecycleTest` | reschedule, cancel, cancellation window, reminders |
| `WhatsAppRequestsAndWeekViewTest` | the fallback and the week calendar |
| `BookingDashboardTest` | services CRUD, bookings inbox, tenancy |

`AvailabilitySettingsTest` deliberately never inserts `WorkingHour` rows directly. That is the
whole point of it.

---

## Known gaps

- PublishReadiness does not yet check that opening hours are set, so a service seller can publish
  a page whose booking widget can never return a slot.
- Rescheduling is a seller-side action; a buyer cannot move their own appointment.
- Reminders are email only; the wa.me link is manual until the WhatsApp Cloud API ships.