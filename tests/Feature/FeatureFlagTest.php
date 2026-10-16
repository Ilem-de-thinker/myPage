<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Billing\Models\FeatureFlag;
use App\Domains\Billing\Services\Feature;
use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;

/**
 * Features ship dark. The whole point of a per-seller row is to let someone be
 * opted in without a deploy, so "a row cannot turn a feature on" is a bug and
 * not a safety feature.
 */
function sellerId(): int
{
    return Seller::factory()->create()->id;
}

function setFlag(int $sellerId, string $feature, bool $enabled): void
{
    FeatureFlag::updateOrCreate(
        ['seller_id' => $sellerId, 'feature' => $feature],
        ['enabled' => $enabled],
    );

    Feature::flush();
}

beforeEach(function () {
    config([
        'mypage.features' => ['escrow' => false, 'directory' => false, 'custom_domains' => true],
        'mypage.features_locked' => false,
    ]);

    Feature::flush();
});

it('leaves a feature off when the platform has not turned it on', function () {
    $id = sellerId();

    expect(Feature::enabled('escrow', $id))->toBeFalse();
});

it('lets a row opt a single seller into a dark feature', function () {
    $id = sellerId();

    setFlag($id, 'escrow', true);

    expect(Feature::enabled('escrow', $id))->toBeTrue();
});

it('leaves every other seller alone when one is opted in', function () {
    $optedIn = sellerId();
    $everyoneElse = sellerId();

    setFlag($optedIn, 'escrow', true);

    expect(Feature::enabled('escrow', $everyoneElse))->toBeFalse();
});

it('lets a row opt a seller out of a feature the platform has on', function () {
    $id = sellerId();

    expect(Feature::enabled('custom_domains', $id))->toBeTrue();

    setFlag($id, 'custom_domains', false);

    expect(Feature::enabled('custom_domains', $id))->toBeFalse();
});

it('ignores a feature that is not in config at all', function () {
    $id = sellerId();

    setFlag($id, 'time_travel', true);

    // Never on by omission, however loudly a row asks.
    expect(Feature::enabled('time_travel', $id))->toBeFalse();
});

it('lets the panic switch override everything', function () {
    $id = sellerId();
    setFlag($id, 'escrow', true);

    expect(Feature::enabled('escrow', $id))->toBeTrue();

    config(['mypage.features_locked' => true]);

    // Including a row that explicitly says on: this is the switch you reach for
    // when a feature is misbehaving in production.
    expect(Feature::enabled('escrow', $id))->toBeFalse();
});

it('tells an explicit opinion apart from following the default', function () {
    $id = sellerId();

    expect(Feature::hasOverride('escrow', $id))->toBeFalse();

    setFlag($id, 'escrow', true);

    expect(Feature::hasOverride('escrow', $id))->toBeTrue();
});

it('reports every feature for a seller at once', function () {
    $id = sellerId();
    setFlag($id, 'escrow', true);

    expect(Feature::allFor($id))->toBe([
        'escrow' => true,
        'directory' => false,
        'custom_domains' => true,
    ]);
});

it('picks up a change without waiting for the cache to expire', function () {
    $id = sellerId();

    expect(Feature::enabled('escrow', $id))->toBeFalse();

    setFlag($id, 'escrow', true);

    // Cached for five minutes otherwise, which is long enough that an admin
    // toggles a switch and tells the seller it is on when it is not.
    expect(Feature::enabled('escrow', $id))->toBeTrue();
});

it('refuses an unknown feature name from the admin panel', function () {
    $id = sellerId();
    setFlag($id, 'escrow', true);

    actingAs(Seller::factory()->admin()->create())
        ->post(route('admin.features.update'), [
            'seller_id' => $id,
            'feature' => 'not_a_real_feature',
            'enabled' => true,
        ])
        ->assertSessionHasErrors('feature');
});

it('lets an admin turn a feature on for one seller', function () {
    $id = sellerId();

    actingAs(Seller::factory()->admin()->create())
        ->post(route('admin.features.update'), [
            'seller_id' => $id,
            'feature' => 'escrow',
            'enabled' => true,
        ])
        ->assertRedirect();

    expect(Feature::enabled('escrow', $id))->toBeTrue();
});

it('records who changed a feature flag', function () {
    $id = sellerId();
    $admin = Seller::factory()->admin()->create();

    actingAs($admin)->post(route('admin.features.update'), [
        'seller_id' => $id,
        'feature' => 'escrow',
        'enabled' => true,
    ]);

    expect(AdminAuditLog::where('action', 'feature.updated')->count())->toBe(1);
});

it('keeps the flag panel away from non-admins', function () {
    $seller = Seller::factory()->create();

    actingAs($seller)->get(route('admin.features'))->assertNotFound();
});
