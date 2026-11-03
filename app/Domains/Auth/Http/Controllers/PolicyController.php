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
    public function show(Request $request, string $policy): View
    {
        $record = Policy::current($policy);
        $defaults = LegalPolicies::all();
        $fallback = $defaults[$policy] ?? null;

        // The shipped copy is a fallback for a deploy that has not seeded yet,
        // never a way to publish a document that is still marked a draft.
        if ($fallback !== null && (bool) ($fallback['is_draft'] ?? false)) {
            $fallback = null;
        }

        // Admins need to read the lawyer-review draft on the same page a buyer
        // would see it. Everyone else only ever gets the published copy.
        if ($record === null && ($request->user()?->isAdmin() ?? false)) {
            $record = Policy::query()->where('slug', $policy)->latest('id')->first();
        }

        abort_unless($record !== null || $fallback !== null, 404);

        $data = $record?->toPolicyArray() ?? $fallback;
        $data['is_draft'] = (bool) ($data['is_draft'] ?? false);

        return view('themes.shared.pages.policy', [
            'policy' => $data,
            'slug' => $policy,
            'effectiveAt' => $record?->effective_at,
            'termsVersion' => Policy::version('terms'),
            'privacyVersion' => Policy::version('privacy'),
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
