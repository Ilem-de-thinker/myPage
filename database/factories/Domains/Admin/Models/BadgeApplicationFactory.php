<?php

namespace Database\Factories\Domains\Admin\Models;

use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BadgeApplication> */
class BadgeApplicationFactory extends Factory
{
    protected $model = BadgeApplication::class;

    public function definition(): array
    {
        // Unverified, because that is the only state a page can apply from: the
        // admin's decision is what sets verified_at, so a factory page that
        // arrived already trusted would make every approval assertion vacuous.
        $page = StorePage::factory()->unverified()->create();

        return [
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->getKey(),
            'status' => BadgeStatus::Pending,
            'business_name' => 'Demo Ventures Nigeria Ltd',
            'document_type' => 'cac',
            'document_path' => 'badges/demo/cac.pdf',
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => BadgeStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => BadgeStatus::Rejected,
            'rejection_reason' => 'The certificate names a different business.',
            'reviewed_at' => now(),
        ]);
    }
}
