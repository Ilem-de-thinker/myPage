<?php

use App\Domains\Seller\Models\Seller;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('logs a seller in and out', function () {
    $seller = Seller::factory()->create(['password' => 'password123']);

    post('/login', ['email' => $seller->email, 'password' => 'password123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($seller);

    post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('rejects bad credentials', function () {
    $seller = Seller::factory()->create(['password' => 'password123']);

    post('/login', ['email' => $seller->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocks suspended sellers from logging in', function () {
    $seller = Seller::factory()->suspended()->create(['password' => 'password123']);

    post('/login', ['email' => $seller->email, 'password' => 'password123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('sends a password reset link', function () {
    Notification::fake();
    $seller = Seller::factory()->create();

    post('/forgot-password', ['email' => $seller->email])->assertSessionHas('status');

    Notification::assertSentTo(
        $seller,
        ResetPassword::class
    );
});

it('resets the password with a valid token', function () {
    $seller = Seller::factory()->create();
    $token = app('auth.password.broker')->createToken($seller);

    post('/reset-password', [
        'token' => $token,
        'email' => $seller->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertRedirect(route('login'));

    expect(Hash::check('newpassword123', $seller->fresh()->password))->toBeTrue();
});

it('requires login for the dashboard', function () {
    get('/dashboard')->assertRedirect(route('login'));
});

it('redirects guests away from onboarding', function () {
    get('/onboarding/business')->assertRedirect(route('login'));
});
