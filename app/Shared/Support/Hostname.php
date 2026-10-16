<?php

namespace App\Shared\Support;

/**
 * Canonicalising a hostname a seller typed.
 *
 * The host is the security boundary for the whole custom-domain feature: it is
 * what decides which seller's shop gets served. So it is normalised in one place
 * and stored only in canonical form, and anything that could point somewhere we
 * do not control is rejected rather than cleaned up.
 *
 * A seller typing "https://www.My-Shop.com./" and a visitor arriving at
 * "my-shop.com" have to reach the same row, and anything shaped like a path,
 * a port, credentials or an IP address is not a domain at all.
 */
final class Hostname
{
    /**
     * Normalise, or return null when the input cannot be a domain.
     *
     * Returns null rather than throwing so callers can surface a validation
     * message instead of a 500.
     */
    public static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $host = strtolower(trim($value));

        // Someone pasting the whole URL from their address bar is common and
        // harmless; a scheme is stripped, anything after the authority is not.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#', $host) === 1) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        $host = rtrim($host, '.');

        // Credentials, ports and paths are all signs of something that is not a
        // bare domain. None of them may survive into the stored host.
        if ($host === ''
            || str_contains($host, '/')
            || str_contains($host, '@')
            || str_contains($host, ':')
            || str_contains($host, ' ')
            || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        // A label is letters, digits and hyphens, not starting or ending with a
        // hyphen. Checked label by label so a bad middle label is not hidden by a
        // valid first one.
        if (preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $host) !== 1) {
            return null;
        }

        return $host;
    }

    /**
     * The apex and www spellings both point at the same shop.
     *
     * A binding is stored under one canonical host. If a seller connects
     * "shop.com" we also serve "www.shop.com" without a second row, because a
     * visitor typing www is not a different shop.
     */
    public static function withoutWww(string $host): string
    {
        return preg_replace('/^www\./', '', $host) ?? $host;
    }
}
