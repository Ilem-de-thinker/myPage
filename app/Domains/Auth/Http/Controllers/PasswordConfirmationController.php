<?php

namespace App\Domains\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Confirm the signed-in seller's password before a money-routing change.
 *
 * Laravel's password.confirm middleware owns the timeout and the intended-URL
 * dance; this page is only the challenge. Google-only sellers have a random
 * password nobody knows, so they confirm by re-authenticating with Google
 * instead — the callback marks the same session timestamp.
 */
class PasswordConfirmationController extends Controller
{
    public function show(Request $request): View
    {
        $usesGoogle = $request->user()->usesGoogle();

        if ($usesGoogle) {
            // Read by GoogleAuthController after a successful re-auth. Short
            // lived on purpose: it is a flag for this detour, not a permission.
            $request->session()->put('auth.confirm_intent', true);
        }

        return view('auth.confirm-password', [
            'usesGoogle' => $usesGoogle,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();

        if ($seller->usesGoogle()) {
            $request->session()->put('auth.confirm_intent', true);

            return redirect()->route('auth.google.confirm');
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check((string) $request->input('password'), (string) $seller->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password is not right.',
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard'));
    }
}
