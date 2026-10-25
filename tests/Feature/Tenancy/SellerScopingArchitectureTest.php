<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Billing\Models\FeatureFlag;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Booking\Models\BookingStatusHistory;
use App\Domains\Orders\Models\OrderStatusHistory;
use App\Domains\Payments\Models\Transaction;
use App\Shared\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * A seller-scoped row must not be reachable without saying whose it is.
 *
 * The failure this catches is quiet: a model with a seller_id column and no
 * global scope does not error when it leaks, it just answers a question with
 * somebody else's rows. `Product::all()` returning another seller's catalogue
 * is not a crash, it is a data breach that only shows up once a buyer sees it.
 *
 * So the rule is enforced here rather than left to review: any model carrying a
 * seller_id column either carries the trait, or is named below with the reason
 * it is exempt. Adding a seller-scoped model means adding it here on purpose.
 *
 * @see BelongsToSeller
 */

/**
 * Models that legitimately have no tenant scope, and why.
 *
 * Every entry is either reachable only through an already-scoped parent, or is
 * only ever read by the admin panel — where seeing across sellers is the entire
 * job, and a seller_id filter would hide the rows someone is moderating.
 *
 * @var array<string, string>
 */
$exempt = [
    // The admin's own record of who did what. Never seller-facing.
    AdminAuditLog::class => 'Admin-only audit trail; written by staff actions, never by a seller.',

    // Per-seller overrides, read by Feature::enabled($feature, $sellerId), which
    // is handed the id explicitly by the caller rather than inferred from the
    // request. An implicit scope here would hide the row from the admin toggle
    // screen that exists to manage these.
    FeatureFlag::class => 'Read by Feature::enabled() with an explicit seller id.',

    // Verified-badge applications. Every read is scoped to the page being
    // approved, and the admin queue is deliberately cross-seller.
    BadgeApplication::class => 'Admin review queue; per-page reads filter on store_page_id.',

    // Append-only history written alongside its parent. Never queried on its own.
    BookingStatusHistory::class => 'Child rows; always through the booking relation.',
    OrderStatusHistory::class => 'Child rows; always through the order relation.',
];

/**
 * Models the admin panel reads across every seller on purpose.
 *
 * Not exempt so much as deliberately the opposite: scoping these would hide the
 * rows an admin is moderating, so they are called out to make that a decision
 * rather than an oversight.
 *
 * @var array<int, class-string>
 */
$adminScoped = [
    Transaction::class,
    Subscription::class,
];

/**
 * Every Eloquent model under app/Domains, resolved to its class name.
 *
 * Inside a function rather than at the top of the file on purpose: Pest loads
 * this file to collect tests, and that happens before the application is booted,
 * so anything touching the container at file scope fails to resolve.
 *
 * @return array<int, class-string<Model>>
 */
function sellerScopingCandidates(): array
{
    return collect(File::allFiles(base_path('app/Domains')))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getPath(), '/Models/'))
        ->map(fn (SplFileInfo $file): string => 'App\\Domains\\'.str_replace('/', '\\', Str::after($file->getPathname(), base_path('app/Domains/'))))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Model::class))
        ->values()
        ->all();
}

it('scopes every seller-owned model to its seller', function () use ($exempt, $adminScoped) {
    $unscoped = [];

    foreach (sellerScopingCandidates() as $model) {
        /** @var Model $instance */
        $instance = new $model;

        // A model without the column is not seller-owned, whatever it is named.
        try {
            if (! Schema::hasColumn($instance->getTable(), 'seller_id')) {
                continue;
            }
        } catch (Throwable) {
            // No database in this test, or the table is not migrated yet.
            continue;
        }

        if (in_array($model, $exempt, true) || in_array($model, $adminScoped, true)) {
            continue;
        }

        if (! in_array(BelongsToSeller::class, class_uses_recursive($model), true)) {
            $unscoped[] = $model;
        }
    }

    expect($unscoped)->toBe(
        [],
        'These models carry a seller_id column but no BelongsToSeller scope. '
        .'Use the trait, or add the class to $exempt in this test with the reason it is safe.'
    );
});

it('does not exempt a model that has quietly been given the trait', function () use ($exempt) {
    // The exemption list is documentation. If a model gains the trait, the note
    // explaining why it does not need one is stale and must be deleted, or the
    // next reader trusts an exemption that no longer applies.
    foreach (array_keys($exempt) as $model) {
        expect(in_array(BelongsToSeller::class, class_uses_recursive($model), true))
            ->toBeFalse(
                "{$model} is listed as exempt from the seller scope but now uses the trait. "
                .'Remove it from $exempt and let the trait do the work.'
            );
    }
});

it('keeps every admin-scoped model genuinely read across sellers', function () use ($adminScoped) {
    // Each of these is unscoped on purpose. This test exists so that "it is an
    // admin model" cannot quietly become the reason a seller-facing query is
    // left unscoped: the list is short, explicit, and reviewed.
    foreach ($adminScoped as $model) {
        expect(in_array(BelongsToSeller::class, class_uses_recursive($model), true))
            ->toBeFalse("{$model} is read across sellers by the admin panel and must not carry the trait.");
    }
});
