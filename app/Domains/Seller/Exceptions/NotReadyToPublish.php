<?php

namespace App\Domains\Seller\Exceptions;

use RuntimeException;

class NotReadyToPublish extends RuntimeException
{
    /**
     * @param  list<array{key: string, label: string, hint: string, url: ?string}>  $blockers
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('This page is not ready to publish yet.');
    }
}
