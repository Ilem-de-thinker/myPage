<?php

namespace App\Shared\Support;

use App\Domains\Admin\Enums\AdminRole;
use App\Domains\Seller\Models\Seller;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Fan a notification out to every core admin at once.
 */
final class CoreAdmins
{
    public static function send(Notification $notification): void
    {
        $admins = Seller::query()
            ->where(fn ($query) => $query->where('admin_role', AdminRole::CoreAdmin->value)
                ->orWhere('is_admin', true))
            ->get();

        if ($admins->isNotEmpty()) {
            NotificationFacade::send($admins, $notification);
        }
    }
}
