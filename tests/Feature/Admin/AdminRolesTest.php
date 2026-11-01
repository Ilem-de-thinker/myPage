<?php

use App\Domains\Admin\Enums\AdminRole;
use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;

function coreAdminUser(): Seller
{
    return Seller::factory()->admin()->create(['email_verified_at' => now()]);
}

function staffAdminUser(): Seller
{
    return Seller::factory()->staffAdmin()->create(['email_verified_at' => now()]);
}

it('still hides the panel from non-admins', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->get('/admin/team')->assertNotFound();
    actingAs($seller)->get('/admin/settings')->assertNotFound();
});

it('shows the team page to core admins', function () {
    $core = coreAdminUser();

    actingAs($core)->get('/admin/team')
        ->assertOk()
        ->assertSee('Core admin')
        ->assertSee($core->email);
});

it('lets staff use operations but not core controls', function () {
    $staff = staffAdminUser();

    actingAs($staff)->get('/admin')->assertOk();
    actingAs($staff)->get('/admin/stores')->assertOk();
    actingAs($staff)->get('/admin/badges')->assertOk();
    actingAs($staff)->get('/admin/audit')->assertOk();

    foreach (['/admin/settings', '/admin/plans', '/admin/features', '/admin/gateway-health', '/admin/team'] as $url) {
        actingAs($staff)->get($url)->assertForbidden();
    }
});

it('lets staff suspend a store but not change its grace window', function () {
    $staff = staffAdminUser();
    [$seller, $page] = sellerWithPage(['status' => 'published']);

    actingAs($staff)->post(route('admin.stores.suspend', $page), ['reason' => 'Fraud'])
        ->assertRedirect();

    expect($page->fresh()->status->value)->toBe('suspended');

    actingAs($staff)->post(route('admin.stores.grace', $page), ['grace_until' => now()->addWeek()->toDateString()])
        ->assertForbidden();
});

it('lets staff read the team page but not change roles', function () {
    $staff = staffAdminUser();
    $target = Seller::factory()->create();

    actingAs($staff)->get('/admin/team')->assertForbidden();
    actingAs($staff)->post(route('admin.team.update', $target), ['role' => 'staff_admin'])
        ->assertForbidden();

    expect($target->fresh()->isAdmin())->toBeFalse();
});

it('promotes a seller to staff and audits who did it', function () {
    $core = coreAdminUser();
    $seller = Seller::factory()->create();

    actingAs($core)->post(route('admin.team.update', $seller), ['role' => 'staff_admin'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $seller->refresh();

    expect($seller->admin_role)->toBe(AdminRole::StaffAdmin)
        ->and($seller->is_admin)->toBeTrue()
        ->and($seller->isStaffAdmin())->toBeTrue();

    expect(AdminAuditLog::where('action', 'admin.role.granted')
        ->where('admin_seller_id', $core->id)
        ->where('subject_id', $seller->id)
        ->exists())->toBeTrue();
});

it('revokes admin access and audits it', function () {
    $core = coreAdminUser();
    $staff = staffAdminUser();

    actingAs($core)->post(route('admin.team.update', $staff), ['role' => ''])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $staff->refresh();

    expect($staff->admin_role)->toBeNull()
        ->and($staff->is_admin)->toBeFalse()
        ->and($staff->isAdmin())->toBeFalse();

    expect(AdminAuditLog::where('action', 'admin.role.revoked')
        ->where('subject_id', $staff->id)
        ->exists())->toBeTrue();
});

it('refuses to demote the last active core admin', function () {
    $core = coreAdminUser();

    actingAs($core)->post(route('admin.team.update', $core), ['role' => 'staff_admin'])
        ->assertRedirect()
        ->assertSessionHasErrors('role');

    expect($core->fresh()->admin_role)->toBe(AdminRole::CoreAdmin);
    expect(AdminAuditLog::where('action', 'admin.role.revoked')->exists())->toBeFalse();
});

it('allows self-demotion when another core admin remains', function () {
    $core = coreAdminUser();
    $other = coreAdminUser();

    actingAs($core)->post(route('admin.team.update', $core), ['role' => 'staff_admin'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($core->fresh()->admin_role)->toBe(AdminRole::StaffAdmin)
        ->and($other->fresh()->isCoreAdmin())->toBeTrue();
});

it('treats a legacy admin flag as a core admin', function () {
    $legacy = Seller::factory()->create(['is_admin' => true, 'admin_role' => null]);

    expect($legacy->isAdmin())->toBeTrue()
        ->and($legacy->isCoreAdmin())->toBeTrue();
});
