<?php

use App\Domains\Seller\Models\Seller;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('sends a guest to the login screen instead of asking for a password', function () {
    $this->get(route('password.confirm'))->assertRedirect(route('login'));
});

it('asks a password seller to confirm and records the confirmation', function () {
    $seller = Seller::factory()->create();

    $this->actingAs($seller)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('Confirm it is you')
        ->assertSee('Your password');

    session(['url.intended' => route('payments.gateways.index')]);

    $this->actingAs($seller)
        ->post(route('password.confirm'), ['password' => 'password'])
        ->assertRedirect(route('payments.gateways.index'));

    expect(session('auth.password_confirmed_at'))->not->toBeNull();
});

it('does not confirm on a wrong password', function () {
    $seller = Seller::factory()->create();

    $this->actingAs($seller)
        ->post(route('password.confirm'), ['password' => 'not-my-password'])
        ->assertSessionHasErrors('password');

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

it('offers Google as the confirmation for a Google-only seller', function () {
    $seller = Seller::factory()->create(['google_id' => 'google-sub-confirm']);

    $this->actingAs($seller)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('Continue with Google');

    expect(session('auth.confirm_intent'))->toBeTrue();
});

it('records the confirmation when the authenticated Google re-auth comes back', function () {
    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');

    $seller = Seller::factory()->create(['google_id' => 'google-sub-confirm']);

    Socialite::shouldReceive('driver->user')->andReturn(
        (new SocialiteUser)->setRaw([])->map([
            'sub' => 'google-sub-confirm',
            'id' => 'google-sub-confirm',
            'email' => $seller->email,
            'email_verified' => true,
            'name' => $seller->name,
        ]),
    );

    $this->actingAs($seller)
        ->withSession(['auth.confirm_intent' => true])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($seller);

    expect(session('auth.password_confirmed_at'))->not->toBeNull()
        ->and(session('auth.confirm_intent'))->toBeNull();
});

it('refuses the confirmation when a different Google account comes back', function () {
    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');

    $seller = Seller::factory()->create(['google_id' => 'google-sub-confirm']);

    Socialite::shouldReceive('driver->user')->andReturn(
        (new SocialiteUser)->setRaw([])->map([
            'sub' => 'someone-elses-sub',
            'id' => 'someone-elses-sub',
            'email' => 'intruder@example.com',
            'name' => 'Intruder',
        ]),
    );

    $this->actingAs($seller)
        ->withSession(['auth.confirm_intent' => true])
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    expect(session('auth.password_confirmed_at'))->toBeNull()
        ->and(session('auth.confirm_intent'))->not->toBeNull();
});
