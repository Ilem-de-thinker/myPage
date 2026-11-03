<?php

namespace App\Domains\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Let a seller set or change the password they sign in with.
 *
 * Google sellers are created with a random password nobody knows, so there is
 * nothing to confirm: the contract here is "you are already signed in with a
 * verified email", which the route middleware guarantees. Once they choose a
 * password, password_set_at is stamped and any later change must present the
 * current one — otherwise a borrowed session could quietly add a second way in.
 */
class AccountSecurityController extends Controller
{
    public function show(Request $request): View
    {
        $seller = $request->user();

        return view('auth.account-password', [
            'usesGoogle' => $seller->usesGoogle(),
            'hasPassword' => ! $seller->usesGoogle() || $seller->password_set_at !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $seller = $request->user();
        $hasPassword = ! $seller->usesGoogle() || $seller->password_set_at !== null;

        $rules = [
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ];

        if ($hasPassword) {
            $rules['current_password'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        if ($hasPassword && ! Hash::check((string) $data['current_password'], (string) $seller->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That password is not right.',
            ]);
        }

        $seller->forceFill([
            'password' => $data['password'],
            'password_set_at' => now(),
        ])->save();

        return redirect()->route('account.password')->with('status', $hasPassword
            ? 'Your password has been updated.'
            : 'Your password is set. You can now sign in with your email and this password.');
    }
}
