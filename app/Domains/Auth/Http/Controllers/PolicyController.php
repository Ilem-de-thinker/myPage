<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Admin\Models\Policy;
use App\Domains\Admin\Support\LegalPolicies;
use App\Domains\Auth\Services\PolicyConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public function show(string $policy): View
    {
        $record = Policy::current($policy);
        $defaults = LegalPolicies::all();

        abort_unless($record !== null || isset($defaults[$policy]), 404);

        $data = $record?->toPolicyArray() ?? $defaults[$policy];
        $data['is_draft'] = (bool) ($data['is_draft'] ?? false);

        return view('themes.shared.pages.policy', [
            'policy' => $data,
            'slug' => $policy,
            'effectiveAt' => $record?->effective_at,
            'termsVersion' => Policy::currentVersion('terms') ?? config('legal.terms_version'),
            'privacyVersion' => Policy::currentVersion('privacy') ?? config('legal.privacy_version'),
        ]);
    }

    public function consent(Request $request, PolicyConsent $consent): View|RedirectResponse
    {
        $seller = $request->user();
        $pending = $consent->pending($seller);

        if ($pending === []) {
            return redirect()->route('dashboard');
        }

        return view('themes.shared.pages.consent', [
            'pending' => $pending,
        ]);
    }

    public function accept(Request $request, PolicyConsent $consent): RedirectResponse
    {
        $request->validate([
            'accepted' => ['accepted'],
        ]);

        $seller = $request->user();
        $pending = $consent->pending($seller);

        $seller->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => $pending['terms'] ?? $seller->terms_version,
            'privacy_accepted_at' => now(),
            'privacy_version' => $pending['privacy'] ?? $seller->privacy_version,
        ])->save();

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', 'Thanks. Your acceptance of the updated policies is recorded.');
    }

    public function requestData(): View
    {
        return view('themes.shared.pages.data-request');
    }
}
