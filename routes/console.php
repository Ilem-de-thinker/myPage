<?php

use App\Domains\Admin\Jobs\ExpireKycVerifications;
use App\Domains\Admin\Jobs\PurgeExpiredKycDocuments;
use App\Domains\Billing\Jobs\ExpireSubscriptions;
use App\Domains\Booking\Jobs\ExpirePendingBookings;
use App\Domains\Booking\Jobs\SendBookingReminders;
use App\Domains\Orders\Actions\CloseStaleAbandonedCarts;
use App\Domains\Orders\Jobs\RemindAbandonedCarts;
use App\Domains\Payments\Jobs\SweepUnprocessedWebhooks;
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
Schedule::job(new PurgeExpiredKycDocuments)->dailyAt('03:00');
Schedule::job(new ExpireKycVerifications)->dailyAt('03:15');

// A webhook is acknowledged before its queue job runs. Retry stored rows that
// remain unprocessed, including seller order events and platform subscriptions.
Schedule::job(new SweepUnprocessedWebhooks)->dailyAt('02:00');
Schedule::command('directory:sync-featured')->dailyAt('02:30');

// A buyer's silence must not hold a seller's money forever.

// DNS propagation is measured in hours, so domains waiting on a CNAME are
// re-checked on a timer rather than only when a seller presses the button.
Schedule::command('domains:verify-pending')->hourly();
