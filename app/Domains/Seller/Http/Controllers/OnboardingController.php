<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Seller\Actions\CreateStorePage;
use App\Domains\Seller\Enums\Theme;
use App\Shared\Rules\NigerianPhone;
use App\Shared\Rules\SafeSlug;
use App\Shared\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private const STEPS = ['business', 'theme', 'brand', 'review'];

    public function show(Request $request, string $step): View
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        $data = $request->session()->get('onboarding', []);

        return view("onboarding.{$step}", [
            'data' => $data,
            // The template is chosen on step two, so until then the copy falls
            // back to the store wording rather than guessing at a service page.
            'theme' => Theme::tryFrom($data['theme'] ?? '') ?? Theme::Store,
        ]);
    }

    public function store(Request $request, string $step, CreateStorePage $createStorePage): RedirectResponse
    {
        abort_unless(in_array($step, self::STEPS, true), 404);

        $data = $request->session()->get('onboarding', []);

        switch ($step) {
            case 'business':
                $validated = $request->validate([
                    'name' => ['required', 'string', 'max:120'],
                    'slug' => ['required', new SafeSlug, Rule::unique('store_pages', 'slug')],
                ]);
                $data = array_merge($data, $validated);
                break;

            case 'theme':
                $validated = $request->validate(['theme' => ['required', Rule::enum(Theme::class)]]);
                $data = array_merge($data, $validated);
                break;

            case 'brand':
                $validated = $request->validate([
                    'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                    'whatsapp' => ['required', new NigerianPhone],
                    'city' => ['required', 'string', 'max:120'],
                    'state' => ['required', 'string', 'max:120'],
                    'bio' => ['nullable', 'string', 'max:1000'],
                ]);
                $validated['whatsapp'] = PhoneNumber::normalize($validated['whatsapp']);
                $data = array_merge($data, $validated);
                break;

            case 'review':
                abort_if(empty($data['name']) || empty($data['slug']) || empty($data['theme']) || empty($data['whatsapp']), 422, 'Complete the previous steps first.');

                $page = $createStorePage->handle($request->user(), $data);
                $request->session()->forget('onboarding');

                return redirect()->route('dashboard')->with('status', "Your page {$page->name} is ready as a draft.");
        }

        $request->session()->put('onboarding', $data);

        return redirect()->route('onboarding.show', self::STEPS[array_search($step, self::STEPS, true) + 1]);
    }
}
