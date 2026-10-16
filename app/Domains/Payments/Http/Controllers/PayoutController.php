<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Billing\Models\Escrow;
use App\Domains\Payments\Actions\RequestPayout;
use App\Domains\Payments\Enums\PayoutStatus;
use App\Domains\Payments\Models\Payout;
use App\Domains\Payments\Models\Wallet;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Shared\Support\Money;
use App\Shared\Support\NigerianBanks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * A seller withdrawing their own earnings.
 *
 * Self-service on purpose. Waiting on an admin to release money a seller has
 * already earned is how a marketplace loses its sellers, and it buys nothing:
 * the amount is already the seller's by the time it reaches this screen, because
 * escrow only reaches the wallet once it has been released or refunded to
 * nobody. Admin involvement belongs on the failure path, not the happy one.
 *
 * The available figure is the wallet balance and nothing else, which is why it
 * is safe: held escrow has not entered the wallet yet, and a payout in flight has
 * already left it.
 */
class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $seller = $request->user();
        $wallet = Wallet::forSeller($seller);

        return view('dashboard.payouts.index', [
            'page' => $seller->storePages()->first(),
            'balance' => $wallet->balance_kobo,
            'inFlight' => (int) $wallet->pending_kobo,
            'heldInEscrow' => $this->heldInEscrowKobo($seller->getKey()),
            'minimum' => app(RequestPayout::class)->minimumKobo(),
            'payouts' => Payout::where('seller_id', $seller->getKey())
                ->orderByDesc('id')
                ->paginate(15),
            'succeeded' => Payout::where('seller_id', $seller->getKey())
                ->where('status', PayoutStatus::Paid)
                ->count(),
        ]);
    }

    /**
     * Save the bank details without withdrawing anything.
     *
     * Separate from requesting a payout on purpose: a seller who mistypes a digit
     * should be able to fix it before any money moves, not afterwards.
     */
    public function saveBankDetails(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payout_bank_code' => ['required', 'string', 'max:16'],
            'payout_account_number' => ['required', 'string', 'max:20'],
            'payout_account_name' => ['nullable', 'string', 'max:190'],
        ]);

        abort_unless(NigerianBanks::exists($data['payout_bank_code']), 422);

        $seller = $request->user();

        // Ask the gateway who the account actually belongs to. A payout to the
        // wrong account is unrecoverable, and a name mismatch is the only warning
        // that arrives before the money is gone.
        try {
            $account = app(PaymentGatewayInterface::class)->resolveAccount(
                $data['payout_bank_code'],
                $data['payout_account_number'],
            );
        } catch (\Throwable) {
            // The bank lookup is a network call to a third party. Bad credentials
            // and gateway outages must read as "we could not check that", not as
            // a 500 — a seller staring at a stack trace learns nothing and
            // assumes their account was rejected.
            return back()->withErrors([
                'payout_account_number' => 'We could not check that account with the bank just now. Try again shortly.',
            ]);
        }

        $seller->forceFill([
            'payout_bank_code' => $data['payout_bank_code'],
            'payout_account_number' => $data['payout_account_number'],
            'payout_account_name' => $account['account_name'],
        ])->save();

        return back()->with('status', 'Bank details saved.');
    }

    public function store(Request $request, RequestPayout $requestPayout): RedirectResponse
    {
        $seller = $request->user();

        if (! $seller->payout_bank_code || ! $seller->payout_account_number) {
            return back()->withErrors(['bank' => 'Save your bank details first.']);
        }

        $wallet = Wallet::forSeller($seller);

        $data = $request->validate([
            'amount_kobo' => [
                'required',
                'integer',
                'min:1',
                // Checked here as well as inside RequestPayout: the form's max
                // attribute is a hint for the browser, not a guarantee.
                function (string $attribute, mixed $value, callable $fail) use ($wallet): void {
                    if ((int) $value > $wallet->balance_kobo) {
                        $fail('That is more than you have available.');
                    }
                },
            ],
        ]);

        try {
            $requestPayout->handle(
                $seller,
                (int) $data['amount_kobo'],
                $seller->payout_bank_code,
                $seller->payout_account_number,
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['amount_kobo' => $exception->getMessage()]);
        } catch (\Throwable) {
            // Anything else is the bank transfer call failing underneath us.
            // Say so plainly rather than showing a stack trace; the money has
            // not moved and the seller can safely try again.
            return back()->withErrors([
                'amount_kobo' => 'We could not reach the bank right now. Nothing has been withdrawn — try again shortly.',
            ]);
        }

        return back()->with('status', 'Payout requested. It usually lands within a working day.');
    }

    /**
     * Money that exists but is not the seller's to withdraw yet.
     *
     * Shown so a seller who sold ₦50,000 this week and can only withdraw ₦20,000
     * has an explanation rather than a mystery.
     */
    private function heldInEscrowKobo(int $sellerId): int
    {
        return (int) Escrow::withoutGlobalScopes()
            ->where('seller_id', $sellerId)
            ->held()
            ->sum('amount_kobo');
    }
}
