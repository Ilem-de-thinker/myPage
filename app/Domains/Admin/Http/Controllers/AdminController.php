<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Actions\RecordAdminAction;
use App\Domains\Admin\Actions\ReviewBadgeApplication;
use App\Domains\Admin\Actions\SuspendStorePage;
use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Admin\Models\StoreReport;
use App\Domains\Admin\Models\StoreReportStatus;
use App\Domains\Billing\Models\FeatureFlag;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Services\Feature;
use App\Domains\Directory\Services\DirectoryPlacement;
use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Internal operations. Every action here writes an audit entry first.
 */
class AdminController extends Controller
{
    public function badges(Request $request): View
    {
        $status = $request->query('status');

        return view('admin.badges', [
            'applications' => BadgeApplication::withoutGlobalScopes()
                ->with(['seller', 'storePage'])
                ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->where('status', BadgeStatus::Pending->value))
                ->orderBy('submitted_at')
                ->paginate(20)
                ->withQueryString(),
            'counts' => BadgeApplication::withoutGlobalScopes()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'activeStatus' => $status,
        ]);
    }

    public function decideBadge(
        Request $request,
        BadgeApplication $application,
        ReviewBadgeApplication $review,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,needs_more_info,revoke,reopen'],
            'note' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = $request->user();
        $reason = (string) ($data['rejection_reason'] ?? '');

        match ($data['decision']) {
            'approve' => $review->approve($application, $admin, $data['note'] ?? null),
            'reject' => $review->reject($application, $admin, $reason),
            'needs_more_info' => $review->requestMoreInfo($application, $admin, $reason),
            'revoke' => $review->revoke($application, $admin, $reason),
            'reopen' => $review->reopen($application, $admin, $data['note'] ?? null),
        };

        return back()->with('status', 'Badge application updated.');
    }

    public function badgeDocument(BadgeApplication $application, RecordAdminAction $audit): Response
    {
        abort_unless($application->hasDocument(), 404);

        $audit->handle(
            request()->user(),
            'badge.document.viewed',
            $application,
            'KYC document viewed by admin.',
        );

        $disk = Storage::disk('local');

        if (! $disk->exists($application->document_path)) {
            abort(404);
        }

        $stored = $disk->get($application->document_path);
        $plain = str_starts_with($stored, 'enc:v1:')
            ? Crypt::decryptString(substr($stored, 7))
            : $stored;

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($plain) ?: 'application/octet-stream';

        return response($plain, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="kyc-'.$application->getKey().'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function suspend(Request $request, StorePage $storePage, SuspendStorePage $suspend): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $suspend->handle($storePage, $data['reason'], $request->user());

        return back()->with('status', "{$storePage->name} is suspended.");
    }

    public function reinstate(
        Request $request,
        StorePage $storePage,
        SuspendStorePage $suspend,
    ): RedirectResponse {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $suspend->reinstate($storePage, $data['note'] ?? null, $request->user());

        return back()->with('status', "{$storePage->name} is back to draft. The seller must publish it again.");
    }

    public function updateDirectory(Request $request, StorePage $storePage, RecordAdminAction $audit, DirectoryPlacement $placement): RedirectResponse
    {
        $data = $request->validate([
            'directory_action' => ['required', 'in:approve,suspend,feature,unfeature'],
        ]);

        if ($data['directory_action'] === 'feature' && ! $placement->canFeature($storePage)) {
            return back()->withErrors(['directory_action' => 'Featured placement requires an active Growth or Pro subscription.']);
        }

        match ($data['directory_action']) {
            'approve' => $storePage->update(['is_directory_opted_in' => true]),
            'suspend' => $storePage->update(['is_directory_opted_in' => false, 'is_featured' => false]),
            'feature' => $storePage->update(['is_directory_opted_in' => true, 'is_featured' => true]),
            'unfeature' => $storePage->update(['is_featured' => false]),
        };
        $audit->handle($request->user(), 'directory.'.$data['directory_action'], $storePage, 'Directory listing moderation.');

        return back()->with('status', 'Directory listing updated.');
    }

    public function stores(Request $request): View
    {
        return view('admin.stores', [
            'pages' => StorePage::withoutGlobalScopes()
                ->with(['seller', 'seller.subscriptions.plan'])
                ->when($request->query('q'), function ($query) use ($request) {
                    $like = '%'.$request->input('q').'%';
                    $query->where('name', 'like', $like)->orWhere('slug', 'like', $like);
                })
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'search' => $request->query('q'),
        ]);
    }

    /**
     * Extend one merchant's grace window without touching the platform default.
     */
    public function updateSubscriptionGrace(
        Request $request,
        StorePage $storePage,
        RecordAdminAction $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'grace_until' => ['nullable', 'date', 'after:now'],
        ]);

        $subscription = $storePage->seller?->subscriptions()->first();

        if ($subscription === null) {
            return back()->withErrors(['grace_until' => 'This seller has no subscription record.']);
        }

        $until = filled($data['grace_until'] ?? null)
            ? Carbon::parse($data['grace_until'])
            : null;

        // Written before the change lands, like every other admin decision.
        $audit->handle(
            $request->user(),
            'subscription.grace_override',
            $subscription,
            $until
                ? 'Grace extended until '.$until->toDayDateTimeString().'.'
                : 'Grace override cleared.',
        );

        $subscription->update(['grace_override_until' => $until]);

        return back()->with(
            'status',
            $until
                ? "Grace period for {$storePage->name} extended until {$until->format('j M Y')}."
                : "Grace override for {$storePage->name} cleared.",
        );
    }

    /**
     * Every transaction on the platform, newest first.
     *
     * This is the screen that answers "did the money arrive" when a seller says
     * it did not, so it shows the gateway's own reference next to ours: the pair
     * is what makes a support conversation with Paystack end.
     */
    public function transactions(Request $request): View
    {
        $status = $request->query('status');
        $gateway = $request->query('gateway');
        $search = $request->query('q');

        return view('admin.transactions', [
            'transactions' => Transaction::withoutGlobalScopes()
                ->with('seller')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($gateway, fn ($query) => $query->where('gateway', $gateway))
                ->when($search, fn ($query) => $query->where('reference', 'like', '%'.$search.'%'))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'counts' => Transaction::withoutGlobalScopes()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'totals' => [
                'paid_kobo' => (int) Transaction::withoutGlobalScopes()->where('status', TransactionStatus::Paid->value)->sum('amount_kobo'),
                'gateway' => $gateway,
                'status' => $status,
            ],
            'activeStatus' => $status,
            'activeGateway' => $gateway,
            'search' => $search,
        ]);
    }

    public function reports(Request $request): View
    {
        $status = $request->query('status', StoreReportStatus::Open->value);

        return view('admin.reports', [
            'reports' => StoreReport::query()
                ->with('storePage')
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'counts' => StoreReport::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'activeStatus' => $status,
            'activeCategory' => $request->query('category'),
        ]);
    }

    public function gatewayHealth(): View
    {
        return view('admin.gateway-health', [
            'gateways' => SellerGateway::withoutGlobalScopes()
                ->with(['seller', 'seller.storePages'])
                ->latest('updated_at')
                ->paginate(30),
        ]);
    }

    public function updateReport(Request $request, StoreReport $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(StoreReportStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'resolved_at' => $data['status'] === StoreReportStatus::Open->value ? null : now(),
            'resolved_by_seller_id' => $data['status'] === StoreReportStatus::Open->value ? null : $request->user()->getKey(),
        ]);

        return back()->with('status', 'Store report updated.');
    }

    /**
     * Feature flags per seller.
     *
     * Shows the resolved state rather than the raw rows, because the row and the
     * answer are different things: no row plus a platform default of true means
     * the feature is on, and an admin who cannot see that distinction will try
     * to "fix" a seller who is already enabled.
     */
    public function features(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $sellers = Seller::withoutGlobalScopes()
            ->when($search, fn ($query) => $query->where('email', 'like', '%'.$search.'%'))
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $sellers->getCollection()->each(function (Seller $seller): void {
            $seller->setAttribute('resolved', Feature::allFor($seller->getKey()));
        });

        return view('admin.features', [
            'sellers' => $sellers,
            'features' => array_keys(config('mypage.features', [])),
            'locked' => (bool) config('mypage.features_locked', false),
            'search' => $search,
        ]);
    }

    public function updateFeature(Request $request, RecordAdminAction $audit): RedirectResponse
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            // Checked against config rather than trusted, so a crafted request
            // cannot invent a feature name and start reading as if it existed.
            'feature' => ['required', 'string', Rule::in(array_keys(config('mypage.features', [])))],
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $data['enabled'];

        FeatureFlag::updateOrCreate(
            ['seller_id' => $data['seller_id'], 'feature' => $data['feature']],
            ['enabled' => $enabled],
        );

        // Cached for five minutes; without this the switch reads as on for
        // everyone and an admin tells a seller it is live when it is not.
        Feature::flush();

        $audit->handle(
            $request->user(),
            'feature.updated',
            Seller::withoutGlobalScopes()->findOrFail($data['seller_id']),
            sprintf('%s turned %s for this seller', $data['feature'], $enabled ? 'ON' : 'OFF'),
        );

        return back()->with('status', ucfirst($data['feature']).' updated.');
    }

    public function auditLog(): View
    {
        return view('admin.audit', [
            'entries' => AdminAuditLog::with('admin')
                ->orderByDesc('id')
                ->paginate(30),
        ]);
    }

    /**
     * Plans, and whether the gateway agrees with what we think they cost.
     *
     * The read of the gateway is the reason this page exists. Paystack charges
     * the plan's own amount in place of the one this application sends, so a
     * plan whose two disagree bills the wrong sum — silently, and with nothing
     * else in the system noticing. Showing both numbers side by side turns that
     * into something an operator can see.
     */
    public function plans(PaymentGatewayInterface $gateway): View
    {
        $plans = Plan::query()
            ->orderByDesc('is_featured')
            ->orderBy('price_kobo')
            ->paginate(20)
            ->withQueryString();

        $gatewayPlans = [];

        foreach ($plans->getCollection()->whereNotNull('gateway_plan_code') as $plan) {
            try {
                $gatewayPlans[$plan->getKey()] = $gateway->fetchPlan($plan->gateway_plan_code);
            } catch (Throwable) {
                // Unreachable is a state worth showing, not a reason to fail the
                // page: the local prices are still the ones being quoted.
                $gatewayPlans[$plan->getKey()] = null;
            }
        }

        return view('admin.plans', [
            'plans' => $plans,
            'gatewayPlans' => $gatewayPlans,
            'gateway' => config('mypage.gateway'),
        ]);
    }

    /**
     * Create the plan on the gateway and keep its code.
     *
     * Doing this here rather than by hand is what lets the price live in one
     * place: config seeds the plan, this attaches the gateway to it, and the
     * admin panel is then the only thing that can change either.
     */
    public function createGatewayPlan(Plan $plan, RecordAdminAction $audit, PaymentGatewayInterface $gateway): RedirectResponse
    {
        if ($plan->gateway_plan_code !== null) {
            return back()->withErrors(['plan' => 'This plan is already connected to the gateway.']);
        }

        $audit->handle(
            request()->user(),
            'plan.gateway.create',
            $plan,
            sprintf('%s at %s per %s', $plan->name, Money::format($plan->price_kobo), $plan->interval),
        );

        try {
            $created = $gateway->createPlan($plan->name, $plan->interval, $plan->price_kobo);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['plan' => 'We could not reach the payment provider, so no plan was created.']);
        }

        $plan->update(['gateway_plan_code' => $created['plan_code']]);

        return back()->with('status', "{$plan->name} is now billed automatically.");
    }

    /**
     * Change a plan's price.
     *
     * The gateway is updated before the local row, never after. The reverse order
     * is the trap: a local save that succeeds while the gateway call fails leaves
     * the panel quoting one price and the card charging another, and the seller
     * finds out at the next cycle.
     *
     * Existing subscribers are left alone unless the operator ticks the box,
     * because the gateway re-prices everyone on the plan the moment it is told
     * to. Sellers who agreed to a price keep it.
     */
    public function updatePlanPrice(
        Plan $plan,
        Request $request,
        RecordAdminAction $audit,
        PaymentGatewayInterface $gateway,
    ): RedirectResponse {
        $data = $request->validate([
            'price_kobo' => ['required', 'integer', 'min:5000'],
            'update_existing' => ['sometimes', 'boolean'],
        ]);

        $priceKobo = (int) $data['price_kobo'];
        $updateExisting = (bool) ($data['update_existing'] ?? false);

        if ($priceKobo === $plan->price_kobo) {
            return back()->with('status', 'That is already the price.');
        }

        $audit->handle(
            $request->user(),
            'plan.price.update',
            $plan,
            sprintf(
                '%s: %s -> %s per %s%s',
                $plan->name,
                Money::format($plan->price_kobo),
                Money::format($priceKobo),
                $plan->interval,
                $updateExisting ? ' (existing subscribers re-priced)' : ' (existing subscribers unchanged)',
            ),
        );

        if ($plan->gateway_plan_code === null) {
            $plan->update(['price_kobo' => $priceKobo]);

            return back()->with('status', "{$plan->name} is now {$this->readable($plan, $priceKobo)}. It is not on the gateway yet, so nothing charges automatically.");
        }

        try {
            $result = $gateway->updatePlan(
                $plan->gateway_plan_code,
                $plan->name,
                $plan->interval,
                $priceKobo,
                $updateExisting,
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['plan' => 'We could not reach the payment provider, so the price is unchanged and still being charged.']);
        }

        $plan->update(['price_kobo' => $priceKobo]);

        $affected = $result['affected_subscriptions'];

        return back()->with('status', $affected
            ? "{$plan->name} is now {$this->readable($plan, $priceKobo)}. {$affected} existing subscription(s) were re-priced."
            : "{$plan->name} is now {$this->readable($plan, $priceKobo)}. Existing subscribers keep their price.");
    }

    private function readable(Plan $plan, int $priceKobo): string
    {
        return Money::format($priceKobo).' per '.rtrim($plan->interval, 'ly');
    }
}
