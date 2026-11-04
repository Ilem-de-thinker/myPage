<?php

use App\Domains\Admin\Jobs\ExpireKycVerifications;
use App\Domains\Admin\Jobs\PurgeExpiredKycDocuments;
use App\Domains\Ai\Jobs\FailStaleAiOperations;
use App\Domains\Ai\Jobs\PurgeOrphanedAiStaging;
use App\Domains\Ai\Jobs\ReleaseStaleAiReservations;
use App\Domains\Billing\Jobs\ExpireSubscriptions;
use App\Domains\Booking\Jobs\ExpirePendingBookings;
use App\Domains\Booking\Jobs\SendBookingReminders;
use App\Domains\Catalog\Jobs\PurgeDeletedProducts;
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

// A reservation counts against a merchant's allowance while work is queued.
// Sweep rows whose worker died so their allowance is not held forever.
Schedule::job(new ReleaseStaleAiReservations)->everyTenMinutes();

// A worker that died mid-call would leave the operation "running" and hold a
// slot forever. Fail it so the merchant can retry.
Schedule::job(new FailStaleAiOperations)->everyTenMinutes();

// Staged AI images belong to drafts. Drafts the merchant abandoned must not
// keep their originals forever.
Schedule::job(new PurgeOrphanedAiStaging)->dailyAt('04:00');
Schedule::command('directory:sync-featured')->dailyAt('02:30');

// Deleted products are recoverable for 30 days; after that the row and its
// images are removed for good.
Schedule::job(new PurgeDeletedProducts)->dailyAt('03:30');

// A buyer's silence must not hold a seller's money forever.

// DNS propagation is measured in hours, so domains waiting on a CNAME are
// re-checked on a timer rather than only when a seller presses the button.
Schedule::command('domains:verify-pending')->hourly();
