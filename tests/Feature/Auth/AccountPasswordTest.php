<?php

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

function googleOnlySeller(): Seller
{
    $seller = Seller::factory()->create([
        'google_id' => 'google-123',
        'password' => 'placeholder-'.Str::random(20),
    ]);
    StorePage::factory()->for($seller)->create();
    $seller->forceFill(['password_set_at' => null, 'email_verified_at' => now()])->save();

    return $seller;
}

it('lets a google seller set a password without knowing the placeholder one', function () {
    $seller = googleOnlySeller();

    actingAs($seller)->get(route('account.password'))
        ->assertOk()
        ->assertSee('Set a password')
        ->assertDontSee('Current password');

    actingAs($seller)->post(route('account.password.update'), [
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ])->assertRedirect(route('account.password'));

    $seller->refresh();

    expect(Hash::check('new-secret-password', $seller->password))->toBeTrue()
        ->and($seller->password_set_at)->not->toBeNull();
});

it('requires the current password once a google seller has chosen one', function () {
    $seller = googleOnlySeller();
    $seller->forceFill(['password' => 'chosen-secret', 'password_set_at' => now()])->save();

    actingAs($seller)->post(route('account.password.update'), [
        'password' => 'another-secret',
        'password_confirmation' => 'another-secret',
    ])->assertSessionHasErrors('current_password');

    actingAs($seller)->post(route('account.password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'another-secret',
        'password_confirmation' => 'another-secret',
    ])->assertSessionHasErrors('current_password');

    actingAs($seller)->post(route('account.password.update'), [
        'current_password' => 'chosen-secret',
        'password' => 'another-secret',
        'password_confirmation' => 'another-secret',
    ])->assertRedirect(route('account.password'));

    expect(Hash::check('another-secret', $seller->fresh()->password))->toBeTrue();
});

it('requires the current password for an email seller', function () {
    $seller = Seller::factory()->create([
        'password' => 'original-secret',
    ]);
    StorePage::factory()->for($seller)->create();

    actingAs($seller)->get(route('account.password'))
        ->assertOk()
        ->assertSee('Current password');

    actingAs($seller)->post(route('account.password.update'), [
        'password' => 'another-secret',
        'password_confirmation' => 'another-secret',
    ])->assertSessionHasErrors('current_password');
});
