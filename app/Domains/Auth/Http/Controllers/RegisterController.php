<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Actions\RegisterSeller;
use App\Domains\Auth\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, RegisterSeller $register): RedirectResponse
    {
        $seller = $register->handle($request->validated());

        Auth::login($seller);
        $request->session()->regenerate();
        $request->session()->put('onboarding', [
            'ai_enabled' => $request->boolean('ai_enabled'),
            'ai_goal' => $request->input('ai_goal'),
        ]);

        return redirect()->route('verification.notice');
    }
}
