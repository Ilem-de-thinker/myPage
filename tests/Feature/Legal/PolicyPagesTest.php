<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\Policy;
use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;

function legalPolicy(array $attributes = []): Policy
{
    return Policy::create([
        'slug' => 'terms',
        'title' => 'Terms of Service',
        'summary' => 'Current terms.',
        'sections' => [['heading' => 'Platform role', 'body' => 'The platform provides software only.']],
        'version' => '2026-11-01',
        'effective_at' => today(),
        'published_at' => now(),
        ...$attributes,
    ]);
}

it('serves the shipped policy copy before the database is seeded', function () {
    actingAs(Seller::factory()->create())
        ->get(route('legal.policy', 'terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('What mypage.ng provides');
});

it('serves the published database copy and its version instead of the fallback', function () {
    legalPolicy();

    actingAs(Seller::factory()->create())
        ->get(route('legal.policy', 'terms'))
        ->assertOk()
        ->assertSee('The platform provides software only.')
        ->assertSee(today()->translatedFormat('j F Y'));
});

it('never shows a draft policy to the public but lets an admin preview it', function () {
    legalPolicy([
        'slug' => 'kyc-verification',
        'title' => 'KYC and Verification Policy',
        'summary' => 'How verification works.',
        'is_draft' => true,
    ]);

    actingAs(Seller::factory()->create())
        ->get(route('legal.policy', 'kyc-verification'))
        ->assertNotFound();

    actingAs(Seller::factory()->coreAdmin()->create())
        ->get(route('legal.policy', 'kyc-verification'))
        ->assertOk()
        ->assertSee('working draft prepared for review');
});

it('asks a seller with an old acceptance to re-accept and records the new version', function () {
    legalPolicy();
    legalPolicy([
        'slug' => 'privacy',
        'title' => 'Privacy Policy',
        'summary' => 'Current privacy terms.',
        'version' => '2026-11-01',
    ]);

    [$seller] = sellerWithPage();
    $seller->forceFill([
        'terms_version' => '2025-01-01',
        'privacy_version' => '2025-01-01',
    ])->save();

    actingAs($seller)->get(route('dashboard'))
        ->assertRedirect(route('legal.consent'));

    actingAs($seller)->get(route('legal.consent'))
        ->assertOk()
        ->assertSee('We updated our policies');

    actingAs($seller)->post(route('legal.consent.accept'), ['accepted' => '1'])
        ->assertRedirect(route('dashboard'));

    expect($seller->fresh()->terms_version)->toBe('2026-11-01')
        ->and($seller->fresh()->privacy_version)->toBe('2026-11-01');
});

it('does not trap a seller who has no recorded acceptance yet', function () {
    legalPolicy();

    [$seller] = sellerWithPage();

    actingAs($seller)->get(route('dashboard'))->assertOk();
});

it('lets a core admin edit a policy and records the change', function () {
    $admin = Seller::factory()->coreAdmin()->create();
    $policy = legalPolicy();

    actingAs($admin)->post(route('admin.policies.update', $policy), [
        'title' => 'Terms of Service',
        'summary' => 'Updated terms.',
        'version' => '2026-12-01',
        'effective_at' => '2026-12-01',
        'sections' => json_encode([
            ['heading' => 'Platform role', 'body' => 'The platform provides software only.'],
            ['heading' => 'Seller duty', 'body' => 'The seller fulfils every order.'],
        ]),
        'published' => '1',
        'is_draft' => '0',
    ])->assertRedirect(route('admin.policies.edit', $policy));

    expect($policy->fresh()->version)->toBe('2026-12-01')
        ->and($policy->fresh()->sections)->toHaveCount(2);

    expect(AdminAuditLog::where('action', 'policy.updated')->count())->toBe(1);
});

it('renders store-level policy pages from the merchant details', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published', 'public_email' => 'help@ada.ng']);

    actingAs($seller)->get(storeUrl($page->slug, '/policies/refunds'))
        ->assertOk()
        ->assertSee('Refunds and Returns')
        ->assertSee('help@ada.ng');
});

it('lets a merchant override a store policy through settings', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published']);
    $page->forceFill([
        'settings' => [
            'policies' => [
                'refunds' => 'No refunds after seven days.',
            ],
        ],
    ])->save();

    actingAs($seller)->get(storeUrl($page->slug, '/policies/refunds'))
        ->assertOk()
        ->assertSee('No refunds after seven days.');
});

it('404s an unknown store policy', function () {
    [, $page] = sellerWithPage(['status' => 'published']);

    actingAs($page->seller)->get(storeUrl($page->slug, '/policies/warranty'))->assertNotFound();
});

it('walls onboarding and support behind the consent screen', function () {
    legalPolicy();
    legalPolicy([
        'slug' => 'privacy',
        'title' => 'Privacy Policy',
        'summary' => 'Current privacy terms.',
        'version' => '2026-11-01',
    ]);

    [$seller] = sellerWithPage();
    $seller->forceFill([
        'terms_version' => '2025-01-01',
        'privacy_version' => '2025-01-01',
    ])->save();

    actingAs($seller)->get(route('onboarding.show', 'business'))->assertRedirect(route('legal.consent'));
    actingAs($seller)->get(route('support.index'))->assertRedirect(route('legal.consent'));
    actingAs($seller)->post(route('support.store'), ['subject' => 'Help', 'message' => 'Hello'])->assertRedirect(route('legal.consent'));
});

it('rejects a policy version that is not a calendar date', function () {
    $admin = Seller::factory()->coreAdmin()->create();
    $policy = legalPolicy();

    actingAs($admin)->post(route('admin.policies.update', $policy), [
        'title' => 'Terms of Service',
        'summary' => 'Updated terms.',
        'version' => 'v2',
        'sections' => json_encode([['heading' => 'Platform role', 'body' => 'The platform provides software only.']]),
        'published' => '1',
        'is_draft' => '0',
    ])->assertSessionHasErrors('version');
});

it('rejects a published policy version that moves backwards', function () {
    $admin = Seller::factory()->coreAdmin()->create();
    $policy = legalPolicy(['version' => '2026-11-01']);

    actingAs($admin)->post(route('admin.policies.update', $policy), [
        'title' => 'Terms of Service',
        'summary' => 'Updated terms.',
        'version' => '2025-01-01',
        'sections' => json_encode([['heading' => 'Platform role', 'body' => 'The platform provides software only.']]),
        'published' => '1',
        'is_draft' => '0',
    ])->assertSessionHasErrors('version');

    expect($policy->fresh()->version)->toBe('2026-11-01');
});

it('lets an admin keep a newer version unpublished as a draft', function () {
    $admin = Seller::factory()->coreAdmin()->create();
    $policy = legalPolicy(['version' => '2026-11-01']);

    actingAs($admin)->post(route('admin.policies.update', $policy), [
        'title' => 'Terms of Service',
        'summary' => 'Draft terms.',
        'version' => '2026-12-15',
        'sections' => json_encode([['heading' => 'Platform role', 'body' => 'The platform provides software only.']]),
        'published' => '1',
        'is_draft' => '1',
    ])->assertRedirect(route('admin.policies.edit', $policy));

    expect($policy->fresh()->is_draft)->toBeTrue();
});
