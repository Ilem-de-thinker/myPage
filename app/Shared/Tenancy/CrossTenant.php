<?php

namespace App\Shared\Tenancy;

/**
 * Seller-owned tables carry a tenant global scope. The admin panel has to read
 * across tenants to review stores, badges and disputes at all.
 *
 * This is a deliberate, explicit switch rather than something an admin's own
 * role implies everywhere: it is turned on by the admin middleware and only for
 * that request, so an admin browsing their own dashboard still sees their shop.
 */
final class CrossTenant
{
    private const FLAG = 'seller.cross_tenant';

    public static function enable(): void
    {
        app()->instance(self::FLAG, true);
    }

    public static function enabled(): bool
    {
        return (bool) app()->bound(self::FLAG) && app(self::FLAG) === true;
    }
}
