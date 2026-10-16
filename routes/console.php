<?php

use App\Domains\Billing\Jobs\ExpireSubscriptions;
use App\Domains\Billing\Jobs\ReleaseAutoEscrow;
use App\Domains\Booking\Jobs\ExpirePendingBookings;
use App\Domains\Booking\Jobs\SendBookingReminders;
use App\Domains\Orders\Actions\CloseStaleAbandonedCarts;
use App\Domains\Orders\Jobs\RemindAbandonedCarts;
use Illuminate\Support\Facades\Schedule;

/*
 * Buyer-facing automation. Everything here is safe to run often: each job is
 * idempotent and only touches rows it has not already handled.
 */

// A hold is fifteen minutes, so a slot must be freed the minute it lapses.
Schedule::job(new ExpirePendingBookings)->everyMinute();

// Each window is half an hour wide and the schedule runs every fifteen minutes,
// so a booking is picked up exactly once despite the overlap.
Schedule::job(new SendBookingReminders(hoursBefore: 24))->everyFifteenMinutes();
Schedule::job(new SendBookingReminders(hoursBefore: 2))->everyFifteenMinutes();

// Nudge buyers who started a checkout and walked away. Hourly so a cart that
// ages past the threshold is not left sitting until tomorrow.
Schedule::job(new RemindAbandonedCarts)->hourly();

// Keep the seller's list to carts still worth chasing.
Schedule::job(new CloseStaleAbandonedCarts)->daily();

// Wind down lapsed subscriptions once their grace period is spent. This only
// records the truth; the storefront is never gated on it.
Schedule::job(new ExpireSubscriptions)->daily();

// A buyer's silence must not hold a seller's money forever.
Schedule::job(new ReleaseAutoEscrow)->daily();

// DNS propagation is measured in hours, so domains waiting on a CNAME are
// re-checked on a timer rather than only when a seller presses the button.
Schedule::command('domains:verify-pending')->hourly();
