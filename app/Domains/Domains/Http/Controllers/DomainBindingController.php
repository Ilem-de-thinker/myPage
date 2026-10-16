<?php

namespace App\Domains\Domains\Http\Controllers;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Jobs\VerifyDomainDns;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Domains\Services\DomainVerifier;
use App\Domains\Seller\Services\SlugResolver;
use App\Shared\Support\Hostname;
use App\Shared\Support\ReservedHostnames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A seller pointing their own domain at their shop.
 *
 * A domain is not switched on the moment it is typed. It is claimed, proved by
 * DNS, and only then served — see DomainVerifier. Everything here is the seller's
 * side of that: add the hostname, see the record they need to create, and check
 * when they say they have done it.
 */
class DomainBindingController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();

        return view('dashboard.domains.index', [
            'bindings' => DomainBinding::where('seller_id', $seller->getKey())
                ->latest()
                ->get(),
            'cnameTarget' => config('mypage.custom_domain.cname_target'),
            'eligible' => $this->planAllows($seller),
            'requiredPlan' => $this->requiredPlanName(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $seller = $request->user();

        if (! $this->planAllows($seller)) {
            return back()->with('error', "A {$this->requiredPlanName()} plan is needed to use your own domain.");
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
        ]);

        // Checked before normalising away the failure: withoutWww('') is '',
        // which is falsy but not null, so a null check here would let junk
        // through as an empty host.
        $normalised = Hostname::normalise($validated['domain']);

        if ($normalised === null) {
            throw ValidationException::withMessages([
                'domain' => 'Enter just the domain, like shop.com. Do not include https:// or a path.',
            ]);
        }

        $host = Hostname::withoutWww($normalised);

        if (ReservedHostnames::isReserved($host)) {
            throw ValidationException::withMessages([
                'domain' => 'That domain belongs to mypage.ng. Use a domain you own.',
            ]);
        }

        if (ReservedHostnames::isTakenByAnotherSeller($host, $seller->getKey())) {
            throw ValidationException::withMessages([
                'domain' => 'Another shop is already using that domain.',
            ]);
        }

        if (DomainBinding::withoutGlobalScopes()
            ->where('seller_id', $seller->getKey())
            ->where('host', $host)
            ->exists()) {
            return back()->with('status', 'You have already added that domain.');
        }

        $binding = DomainBinding::withoutGlobalScopes()->create([
            'seller_id' => $seller->getKey(),
            'store_page_id' => $seller->storePages()->value('id'),
            'host' => $host,
            // Only used to show the seller which record is theirs if they need
            // to tell their host apart from our other domains.
            'verification_token' => Str::upper(Str::random(10)),
            'status' => DomainStatus::Pending,
        ]);

        VerifyDomainDns::dispatch($binding);

        return back()->with('status', "Added {$host}. Point it at us and it will go live.");
    }

    /**
     * Check one now, on the seller's say-so rather than waiting for the sweep.
     */
    public function verify(Request $request, DomainBinding $binding): RedirectResponse
    {
        $this->authorizeBinding($request, $binding);

        // Synchronous: the seller is looking at this screen waiting for an
        // answer, and one DNS lookup is fast enough to do inline.
        $status = app(DomainVerifier::class)->verify($binding);

        [$key, $message] = match ($status) {
            DomainStatus::Active => ['status', 'Your domain is live.'],
            DomainStatus::Suspended => ['error', 'That domain has stopped pointing at us.'],
            default => ['status', 'Not yet. DNS can take a while to catch up.'],
        };

        return back()->with($key, $message);
    }

    public function destroy(Request $request, DomainBinding $binding): RedirectResponse
    {
        $this->authorizeBinding($request, $binding);

        $host = $binding->host;
        $binding->delete();

        // The cached lookup would otherwise keep serving the shop on a domain
        // the seller has just given up.
        app(SlugResolver::class)->flushHost($host);

        return back()->with('status', "Removed {$host}.");
    }

    /**
     * A seller may only touch their own bindings.
     *
     * The seller scope hides other sellers' rows, so a binding that is not
     * found here was either deleted or never theirs. Either way the answer is
     * the same and reveals nothing.
     */
    private function authorizeBinding(Request $request, DomainBinding $binding): void
    {
        abort_unless($binding->seller_id === $request->user()->getKey(), 404);
    }

    private function planAllows($seller): bool
    {
        $subscription = Subscription::with('plan')->where('seller_id', $seller->getKey())->first();

        return $subscription?->canWrite()
            && in_array($subscription->plan?->slug, $this->eligiblePlans(), true);
    }

    /**
     * @return list<string>
     */
    private function eligiblePlans(): array
    {
        return (array) config('billing.custom_domain_plans', ['pro']);
    }

    private function requiredPlanName(): string
    {
        return ucfirst((string) ($this->eligiblePlans()[0] ?? 'Pro'));
    }
}
