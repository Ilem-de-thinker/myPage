<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Seller\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Signing in with Google instead of a password.
 *
 * Two rules govern everything here, and both exist because a Google sign-in is
 * a stranger's word about who somebody is.
 *
 * One: the Google identity — not the email — decides who you are. Google lets
 * somebody change the address on an account, and the day two accounts share one
 * an email-keyed lookup hands the second one the first one's shop. The `sub` is
 * stable for the life of the Google account.
 *
 * Two: an unverified Google email never gets to open an existing account. If a
 * seller signed up with an email/password and later reaches for Google with the
 * same address but a different Google account, linking them would let whoever
 * holds that Google account walk into a shop they did not build. Same address,
 * different Google identity, no link.
 */
class GoogleAuthController
{
    /**
     * Send the seller to Google.
     *
     * `stateless()` is deliberate and is the difference between this being safe
     * and not: it drops the OAuth state token, which is the one thing that proves
     * the callback belongs to a redirect *this* application started. Without it
     * an attacker can complete an OAuth flow with their own Google account and
     * post the callback at a victim, who is silently signed in as the attacker.
     */
    public function redirect(): RedirectResponse
    {
        abort_if(
            blank(config('services.google.client_id')),
            404,
            'Google sign-in is not configured on this installation.',
        );

        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (InvalidStateException) {
            return $this->failed('Google sign-in expired. Please try again.');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->failed('Google sign-in did not complete. Please try again.');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();

        if (blank($googleId) || blank($email)) {
            return $this->failed('Google did not share enough to sign you in.');
        }

        $seller = Seller::where('google_id', $googleId)->first();

        if ($seller !== null) {
            return $this->signedIn($seller);
        }

        /*
            No seller for this Google identity yet.

            The email decides whether this is a new seller or somebody trying to
            take over an existing one, and only a *verified* email counts as
            continuity — which is the whole point. An unverified Google account
            can be given somebody else's address, so treating an unverified match
            as "the same seller" would let anyone walk into any shop whose address
            they happened to know.
        */
        $existing = Seller::where('email', $email)->first();

        if ($existing !== null) {
            if ($existing->usesGoogle() && $existing->google_id !== $googleId) {
                return $this->failed('That email is already linked to a different Google account.');
            }

            if (! $existing->email_verified_at) {
                return $this->failed('Finish verifying your email with us first, then sign in with Google.');
            }

            // Verified email, same person, new way in. Link and let them through.
            $existing->forceFill([
                'google_id' => $googleId,
                'name' => $existing->name ?: $googleUser->getName(),
            ])->save();

            return $this->signedIn($existing);
        }

        return $this->register($googleUser, $googleId, $email);
    }

    /**
     * A seller arriving for the first time through Google.
     *
     * The seller account has to exist before the rest of onboarding does, so
     * this creates the minimum: an account, no page. They land on the same
     * onboarding steps somebody would have reached by registering, rather than
     * in a half-built state with nothing telling them what to do next.
     */
    private function register(User $googleUser, string $googleId, string $email): RedirectResponse
    {
        /*
            forceFill, not create().

            sellers.email_verified_at is not mass-assignable on purpose — no
            request may ever mark an address verified — and it is exactly the
            field this path has to set. Mass assignment would drop it silently
            and every Google seller would be created unverified, which then fails
            the verified-email check on their next sign-in. The server deciding
            its own fields is the reason to reach past the fill list here.
        */
        $seller = new Seller([
            'name' => $googleUser->getName() ?: Str::before($email, '@'),
            'email' => $email,
            'phone' => null,

            /*
                sellers.password is NOT NULL, so a Google-only seller still needs
                one. A random 40-character string nobody keeps: it satisfies the
                column without becoming a second way in.
            */
            'password' => Str::password(40),

            'status' => 'active',
        ]);

        $seller->forceFill([
            'google_id' => $googleId,

            // Google has already verified this address, so the local OTP is
            // asking the same question twice.
            'email_verified_at' => now(),
        ])->save();

        return $this->signedIn($seller);
    }

    private function signedIn(Seller $seller): RedirectResponse
    {
        if ($seller->status !== 'active') {
            Auth::logout();

            return $this->failed('That account is not active. Get in touch and we will sort it out.');
        }

        Auth::login($seller, remember: true);

        /*
            A Google-only seller confirming a password-gated action. The flag is
            set by PasswordConfirmationController::show() and is only readable
            after Google has just vouched for this identity, so it cannot be used
            to skip a confirmation that was never asked for.
        */
        if (session()->pull('auth.confirm_intent')) {
            session()->put('auth.password_confirmed_at', time());
        }

        $seller->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }
}
