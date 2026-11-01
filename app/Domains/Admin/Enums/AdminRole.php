<?php

namespace App\Domains\Admin\Enums;

enum AdminRole: string
{
    case CoreAdmin = 'core_admin';
    case StaffAdmin = 'staff_admin';

    public function label(): string
    {
        return match ($this) {
            self::CoreAdmin => 'Core admin',
            self::StaffAdmin => 'Staff admin',
        };
    }

    /**
     * Core admins can change the platform itself: money, settings, roles.
     * Everyone else in the panel handles day-to-day operations.
     */
    public function isCore(): bool
    {
        return $this === self::CoreAdmin;
    }
}
