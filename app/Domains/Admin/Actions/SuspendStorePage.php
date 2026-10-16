<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a page off the internet. Used for fraud and for abuse, so it is not a
 * one-click button: a reason is mandatory and the reason is stored.
 *
 * Orders, bookings and the customer book are untouched. A suspended page stops
 * being reachable; it does not delete anyone's trading history.
 */
class SuspendStorePage
{
    public function __construct(private readonly RecordAdminAction $audit) {}

    public function handle(StorePage $page, string $reason, $admin): StorePage
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Record why. This is kept and the seller will be told.',
            ]);
        }

        return DB::transaction(function () use ($page, $reason, $admin) {
            $locked = StorePage::withoutGlobalScopes()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === StoreStatus::Suspended) {
                return $locked;
            }

            $locked->update(['status' => StoreStatus::Suspended]);

            $this->audit->handle($admin, 'store_page.suspended', $locked, $reason);

            return $locked;
        });
    }

    public function reinstate(StorePage $page, ?string $note = null, $admin = null): StorePage
    {
        return DB::transaction(function () use ($page, $note, $admin) {
            $locked = StorePage::withoutGlobalScopes()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== StoreStatus::Suspended) {
                return $locked;
            }

            // Back to draft, not published: an admin should not put a page live
            // that the seller has since broken.
            $locked->update(['status' => StoreStatus::Draft]);

            if ($admin !== null) {
                $this->audit->handle($admin, 'store_page.reinstated', $locked, $note);
            }

            return $locked;
        });
    }
}
