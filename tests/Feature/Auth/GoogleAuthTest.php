<?php

use App\Domains\Seller\Models\Seller;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Laravel\get;

/**
 * Google sign-in for sellers.
 *
 * Most of these are about the one thing that matters: a Google account is a
 * stranger's word about who somebody is, and two accounts must never end up able
 * to open each other's shop.
 */

/**
 * A Socialite user, as Google would hand it back.
 *
 * GoogleProvider maps the OIDC `sub` onto `id` itself (GoogleProvider.php:90),
 * so both are set here — the pair Google actually returns.
 */
function googleUser(string $id, string $email, ?string $name = 'Ada Textiles'): SocialiteUser
{
    return (new SocialiteUser)->setRaw([
        'sub' => $id,
        'email' => $email,
        'email_verified' => true,
    ])->map([
        'sub' => $id,
        'id' => $id,
        'email' => $email,
        'email_verified' => true,
        'name' => $name,
    ]);
}

/** Make the callback resolve to this Google user instead of calling Google. */
function googleSays(string $id, string $email, ?string $name = 'Ada Textiles'): void
{
    Socialite::shouldReceive('driver->user')->andReturn(googleUser($id, $email, $name));
}

function enableGoogle(): void
{
    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');
}

it('signs a returning seller in by their Google identity', function () {
    enableGoogle();

    $seller = Seller::factory()->create([
        'google_id' => 'google-sub-1',
        'email_verified_at' => now(),
    ]);

    googleSays('google-sub-1', $seller->email);

    get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($seller);
});

it('creates a seller the first time somebody arrives through Google', function () {
    enableGoogle();

    googleSays('google-sub-999', 'new@example.com', 'New Shop');

    get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $seller = Seller::where('google_id', 'google-sub-999')->sole();

    expect($seller->email)->toBe('new@example.com')
        // Google already verified it, so the local OTP would ask twice.
        ->and($seller->email_verified_at)->not->toBeNull()
        // password is NOT NULL, so one exists — and it is nobody's.
        ->and($seller->password)->not->toBeNull();
});

/**
 * The takeover this whole controller exists to prevent: somebody holds a Google
 * account they control, sets its address to a victim's, and walks in.
 */
it('refuses to link an unverified Google email to an existing seller', function () {
    enableGoogle();

    $victim = Seller::factory()->create([
        'email' => 'victim@example.com',
        'email_verified_at' => null,
    ]);

    googleSays('attacker-google-id', 'victim@example.com');

    get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();

    // And crucially the seller was not linked to the attacker's Google account.
    expect($victim->fresh()->google_id)->toBeNull();
});

it('refuses a Google account already linked to a different Google identity', function () {
    enableGoogle();

    $seller = Seller::factory()->create([
        'email' => 'seller@example.com',
        'email_verified_at' => now(),
        'google_id' => 'the-original-google-id',
    ]);

    // Same verified email, a different Google account. Continuity of address is
    // not continuity of identity.
    googleSays('a-different-google-id', 'seller@example.com');

    get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect($seller->fresh()->google_id)->toBe('the-original-google-id');
});

it('links a verified existing seller whose address matches', function () {
    enableGoogle();

    $seller = Seller::factory()->create([
        'email' => 'seller@example.com',
        'email_verified_at' => now(),
        'google_id' => null,
    ]);

    googleSays('a-google-id', 'seller@example.com');

    get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($seller);
    expect($seller->fresh()->google_id)->toBe('a-google-id');
});

it('refuses to link when Google does not explicitly verify the email', function () {
    enableGoogle();

    $seller = Seller::factory()->create([
        'email' => 'seller@example.com',
        'email_verified_at' => now(),
        'google_id' => null,
    ]);

    // No email_verified claim at all: "not false" is not the same as "verified",
    // and an existing shop must not be opened on an absent claim.
    $user = (new SocialiteUser)->setRaw([])->map([
        'sub' => 'a-google-id',
        'id' => 'a-google-id',
        'email' => 'seller@example.com',
        'name' => 'Ada Textiles',
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($user);

    get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect($seller->fresh()->google_id)->toBeNull();
});

it('refuses a suspended seller', function () {
    enableGoogle();

    Seller::factory()->create([
        'google_id' => 'google-sub-1',
        'status' => 'suspended',
    ]);

    googleSays('google-sub-1', 'anyone@example.com');

    get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('hides the Google button when there is no client id', function () {
    config()->set('services.google.client_id', null);

    get('/login')->assertOk()->assertDontSee('Continue with Google');
});

it('shows the Google button when one is configured', function () {
    enableGoogle();

    get('/login')->assertOk()->assertSee('Continue with Google');
});

it('404s the sign-in route rather than failing at Google', function () {
    config()->set('services.google.client_id', null);

    get('/auth/google')->assertNotFound();
});

it('does not let a signed-in seller walk the callback again', function () {
    enableGoogle();

    $seller = Seller::factory()->create(['google_id' => 'google-sub-1']);

    $this->actingAs($seller)
        ->get(route('auth.google.callback'))
        ->assertRedirect();
});

it('shows the Google button on login and register when keys are configured', function () {
    enableGoogle();

    get(route('login'))->assertOk()->assertSee('Continue with Google');
    get(route('register'))->assertOk()->assertSee('Continue with Google');
});

it('hides the Google button on login and register when keys are missing', function () {
    config()->set('services.google.client_id', null);

    get(route('login'))->assertOk()->assertDontSee('Continue with Google');
    get(route('register'))->assertOk()->assertDontSee('Continue with Google');
});
