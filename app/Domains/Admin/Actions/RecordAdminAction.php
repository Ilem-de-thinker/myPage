<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Records an admin decision before it lands, not after. If the action below
 * throws, the log still explains what was attempted.
 */
class RecordAdminAction
{
    public function handle(
        Seller $admin,
        string $action,
        ?Model $subject = null,
        ?string $note = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_seller_id' => $admin->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'note' => $note,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * The same record, with no seller attached, for scheduled work.
     */
    public function system(string $action, ?Model $subject = null, ?string $note = null): AdminAuditLog
    {
        return AdminAuditLog::create([
            'admin_seller_id' => null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'note' => $note,
        ]);
    }
}
