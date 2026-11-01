<?php

use App\Domains\Auth\Events\SellerRegistered;
use App\Domains\Auth\Notifications\EmailOtpNotification;
use App\Domains\Auth\Notifications\WelcomeNotification;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows the registration form', function () {
    get('/register')->assertOk();
});

it('registers a seller and redirects to email verification', function () {
    Notification::fake();

    $response = post('/register', [
        'name' => 'Ada Obi',
        'email' => 'ada@example.com',
        'phone' => '0803 123 4567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'terms_accepted' => true,
        'privacy_accepted' => true,
    ]);

    $response->assertRedirect(route('verification.notice'));

    $seller = Seller::where('email', 'ada@example.com')->first();
    expect($seller)->not->toBeNull()
        ->and($seller->phone)->toBe('+2348031234567')
        ->and($seller->email_verified_at)->toBeNull();

    $this->assertAuthenticated();
});

it('verifies email with the OTP code', function () {
    Notification::fake();

    post('/register', [
        'name' => 'Ada', 'email' => 'ada@example.com', 'phone' => '0803 123 4567',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'terms_accepted' => true, 'privacy_accepted' => true,
    ]);

    Notification::assertSentTo(Seller::first(), EmailOtpNotification::class);

    $seller = Seller::first();
    $code = null;
    Notification::assertSentTo($seller, EmailOtpNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    post('/email/verify', ['code' => $code])->assertRedirect(route('dashboard'));
    expect($seller->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a wrong OTP code', function () {
    $seller = Seller::factory()->unverified()->create();
    $seller->sendEmailVerificationNotification();

    $this->actingAs($seller)->post('/email/verify', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($seller->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('normalises phone numbers on registration', function (string $input, string $expected) {
    Notification::fake();

    post('/register', [
        'name' => 'Ada', 'email' => fake()->safeEmail(), 'phone' => $input,
        'password' => 'password123', 'password_confirmation' => 'password123',
        'terms_accepted' => true, 'privacy_accepted' => true,
    ]);

    expect(Seller::latest()->first()->phone)->toBe($expected);
})->with([
    ['0803 123 4567', '+2348031234567'],
    ['+234 803 123 4567', '+2348031234567'],
    ['2348031234567', '+2348031234567'],
]);

it('rejects duplicate phone numbers', function () {
    Seller::factory()->create(['phone' => '+2348031234567']);

    post('/register', [
        'name' => 'Ada', 'email' => 'new@example.com', 'phone' => '+2348031234567',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'terms_accepted' => true, 'privacy_accepted' => true,
    ])->assertSessionHasErrors('phone');
});

it('rejects invalid phone numbers', function () {
    post('/register', [
        'name' => 'Ada', 'email' => 'new@example.com', 'phone' => '12345',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'terms_accepted' => true, 'privacy_accepted' => true,
    ])->assertSessionHasErrors('phone');
});

it('welcomes a new seller with the first step they have to take', function () {
    Notification::fake();

    post('/register', [
        'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '0803 123 4567',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'terms_accepted' => true, 'privacy_accepted' => true,
    ]);

    $seller = Seller::where('email', 'ada@example.com')->firstOrFail();

    Notification::assertSentTo($seller, WelcomeNotification::class, function (WelcomeNotification $notification, array $channels) {
        return $channels === ['mail']
            && $notification->firstStepUrl === route('onboarding.show', 'business');
    });
});

it('does not welcome a seller who has already verified', function () {
    Notification::fake();

    $seller = Seller::factory()->create();

    event(new SellerRegistered($seller));

    Notification::assertNothingSent();
});
