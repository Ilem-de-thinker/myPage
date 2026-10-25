<?php

namespace App\Domains\Payments\Exceptions;

use RuntimeException;

/**
 * The gateway answered, and the answer was no.
 *
 * Distinct from GatewayVerificationUnavailable because the seller can act on
 * this one: re-copy the key. A network failure is our problem, not theirs.
 */
class InvalidGatewayCredentials extends RuntimeException
{
    public static function rejectedBy(string $gateway): self
    {
        return new self("{$gateway} rejected that secret key.");
    }

    public static function wrongEnvironment(string $gateway, bool $production): self
    {
        return new self($production
            ? "{$gateway} live keys are required in production. A test key cannot take real payments."
            : "{$gateway} test keys are required here. A live key must only be used in production.");
    }

    public static function malformed(string $gateway): self
    {
        return new self("That does not look like a {$gateway} secret key. Check you copied the secret key, not the public one.");
    }
}
