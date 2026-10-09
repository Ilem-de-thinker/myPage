<?php

namespace App\Domains\Auth\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        if (request()->user()?->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $seller = $request->user();

        if ($seller->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        if (! $seller->verifyEmailOtp($request->input('code'))) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.']);
        }

        event(new Verified($seller));

        return redirect()->route('dashboard')->with('status', 'Email verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification code has been sent.');
    }
}
