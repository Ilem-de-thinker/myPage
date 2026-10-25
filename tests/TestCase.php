<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * A request to an absolute URL — a shop's subdomain, or a seller's own
         * domain — changes the URL generator's root for every call after it. A
         * later relative request would then be addressed to the shop, where
         * dashboard paths correctly 404. Pinning the root keeps each test's
         * "the platform" and "the shop" distinct.
         */
        URL::forceRootUrl(config('app.url'));
    }
}
