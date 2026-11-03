<?php

namespace App\Domains\Auth\Services;

use App\Domains\Admin\Models\Policy;
use App\Domains\Seller\Models\Seller;

class PolicyConsent
{
    /**
     * The accepted version of each policy that no longer matches the published
     * one. A null acceptance is left alone: factory-created and legacy accounts
     * predate the consent columns, and they should not be trapped on a consent
     * screen they never had a chance to complete.
     *
     * @return array<string, string>
     */
    public function pending(Seller $seller): array
    {
        $pending = [];

        $terms = Policy::version('terms');
        $privacy = Policy::version('privacy');

        if ($terms !== null && $seller->terms_version !== null && $seller->terms_version !== $terms) {
            $pending['terms'] = $terms;
        }

        if ($privacy !== null && $seller->privacy_version !== null && $seller->privacy_version !== $privacy) {
            $pending['privacy'] = $privacy;
        }

        return $pending;
    }
}
