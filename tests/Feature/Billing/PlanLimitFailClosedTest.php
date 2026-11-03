<?php

use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Catalog\Models\Product;
use Database\Seeders\PlanSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('fails closed to the entry-plan limit when a seller has no subscription row', function () {
    $this->seed(PlanSeeder::class);
    config()->set('billing.plans.trial.max_products', 2);

    [$seller, $page] = sellerWithPage();

    $entitlements = app(SubscriptionEntitlements::class);

    expect($entitlements->listingLimit($page))->toBe(2);

    Product::factory()->count(2)->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    try {
        $entitlements->assertCanCreateListing($page);
        $this->fail('Expected the entry-plan limit to block the third listing.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});

it('does not enforce limits when the subscription programme is disabled', function () {
    app(SubscriptionAvailability::class)->disable();

    [$seller, $page] = sellerWithPage();

    $entitlements = app(SubscriptionEntitlements::class);

    expect($entitlements->listingLimit($page))->toBeNull()
        ->and($entitlements->staffLimit($page))->toBeNull();
});
