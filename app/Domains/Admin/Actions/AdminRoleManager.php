<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Enums\AdminRole;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changing a role is the one admin action that can lock the platform team out
 * of its own panel, so it runs under a row lock and refuses to remove the last
 * active core admin.
 */
class AdminRoleManager
{
    public function __construct(private readonly RecordAdminAction $audit) {}

    public function assign(Seller $target, ?AdminRole $role, Seller $actor): Seller
    {
        return DB::transaction(function () use ($target, $role, $actor): Seller {
            $seller = Seller::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($target->getKey());

            $previous = $seller->adminRole();

            if ($previous === $role) {
                // Same role, possibly a legacy row whose boolean had not been
                // written into the role column yet.
                if ($seller->admin_role !== $role) {
                    $seller->forceFill(['admin_role' => $role, 'is_admin' => $role !== null])->save();
                }

                return $seller;
            }

            if ($previous === AdminRole::CoreAdmin && $role !== AdminRole::CoreAdmin) {
                $remainingCores = Seller::withoutGlobalScopes()
                    ->where('status', 'active')
                    ->where(fn ($query) => $query
                        ->where('admin_role', AdminRole::CoreAdmin->value)
                        // Legacy rows are core by fallback even before the
                        // backfill catches up.
                        ->orWhere(fn ($legacy) => $legacy->whereNull('admin_role')->where('is_admin', true)))
                    ->whereKeyNot($seller->getKey())
                    ->lockForUpdate()
                    ->count();

                if ($remainingCores === 0) {
                    throw ValidationException::withMessages([
                        'role' => 'There must always be at least one active core admin.',
                    ]);
                }
            }

            // Written before the change lands, like every other admin decision.
            $this->audit->handle(
                $actor,
                $this->actionFor($previous, $role),
                $seller,
                sprintf(
                    'Admin role: %s -> %s',
                    $previous?->label() ?? 'not an admin',
                    $role?->label() ?? 'not an admin',
                ),
            );

            $seller->forceFill([
                'admin_role' => $role,
                'is_admin' => $role !== null,
            ])->save();

            return $seller;
        });
    }

    private function actionFor(?AdminRole $previous, ?AdminRole $role): string
    {
        return match (true) {
            $role === null => 'admin.role.revoked',
            $previous === null => 'admin.role.granted',
            default => 'admin.role.changed',
        };
    }
}
