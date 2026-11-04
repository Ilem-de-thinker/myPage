<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Payments\Actions\SaveSellerGateway;
use App\Domains\Payments\Exceptions\GatewayVerificationUnavailable;
use App\Domains\Payments\Exceptions\InvalidGatewayCredentials;
use App\Domains\Payments\Models\SellerGateway;
use App\Shared\Support\StorefrontMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The seller's side of bringing their own gateway keys.
 *
 * Buyer money settles in the seller's own gateway account, so this screen is
 * what makes a store able to take money at all. It never reads a key back into
 * the page: the masked form is the only representation the seller sees after
 * saving, and replacing one means typing a new key rather than editing the old.
 */
class SellerGatewayController extends Controller
{
    private const GATEWAYS = ['paystack', 'flutterwave'];

    public function index(Request $request): View
    {
        $gateways = SellerGateway::withoutGlobalScopes()
            ->where('seller_id', $request->user()->getKey())
            ->get()
            ->keyBy('gateway');

        $page = $request->user()->storePages()->first();

        return view('dashboard.payments.gateways', [
            'gateways' => $gateways,
            'names' => [
                'paystack' => 'Paystack',
                'flutterwave' => 'Flutterwave',
            ],
            'environments' => [
                'expected' => app()->isProduction() ? 'live' : 'test',
            ],
            'webhookBase' => $page
                ? (StorefrontMode::usesPaths()
                    ? (string) config('mypage.domain')
                    : $page->slug.'.'.config('mypage.domain'))
                : null,
        ]);
    }

    public function store(Request $request, SaveSellerGateway $save): RedirectResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'string', Rule::in(self::GATEWAYS)],
            'public_key' => ['nullable', 'string', 'max:255'],
            /*
             * No max rule on the secrets: a length failure would flash the value
             * into the session as old input, and these two are the one pair of
             * fields that must never take that trip. Length is checked by the
             * gateway anyway, which is the authority on its own key format.
             */
            'secret_key' => ['required', 'string'],
            'webhook_secret' => ['nullable', 'string'],
        ]);

        try {
            $save->handle(
                $request->user(),
                $data['gateway'],
                $data['secret_key'],
                $data['webhook_secret'] ?? null,
                $data['public_key'] ?? null,
            );
        } catch (InvalidGatewayCredentials $exception) {
            return back()
                ->withErrors(['secret_key' => $exception->getMessage()])
                ->withInput($request->except(['secret_key', 'webhook_secret']));
        } catch (GatewayVerificationUnavailable $exception) {
            return back()
                ->withErrors(['secret_key' => $exception->getMessage().' Nothing was saved; try again shortly.'])
                ->withInput($request->except(['secret_key', 'webhook_secret']));
        }

        $name = $data['gateway'] === 'flutterwave' ? 'Flutterwave' : 'Paystack';

        return back()->with('status', "{$name} key verified and saved.");
    }

    public function destroy(Request $request, string $gateway): RedirectResponse
    {
        abort_unless(in_array($gateway, self::GATEWAYS, true), 404);

        $record = SellerGateway::withoutGlobalScopes()
            ->where('seller_id', $request->user()->getKey())
            ->where('gateway', $gateway)
            ->firstOrFail();

        $record->delete();

        return back()->with('status', 'Key removed. Your store cannot take payments until you add one again.');
    }
}
