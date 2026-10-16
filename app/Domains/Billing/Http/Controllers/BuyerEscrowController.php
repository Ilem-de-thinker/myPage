<?php

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Billing\Actions\EscrowService;
use App\Domains\Billing\Models\Escrow;
use App\Domains\Billing\Models\EscrowStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The buyer's side of escrow.
 *
 * Unauthenticated by design: the seller already trusts strangers with a payment
 * link, and asking a buyer to create an account to release their own money is
 * how a hold ends up expiring by accident. Access is instead proved by a signed
 * token, so the URL is the capability. It is not guessable, it only ever opens
 * one order, and every write goes through EscrowService, which refuses to move
 * money twice.
 */
class BuyerEscrowController extends Controller
{
    public function show(StorePage $page, Request $request, string $token): View
    {
        $escrow = $this->findEscrow($page, $token);

        abort_if($escrow === null, 404);

        $order = $this->orderFor($escrow);

        return view('themes.store.escrow', [
            'page' => $page,
            'escrow' => $escrow,
            'order' => $order,
        ]);
    }

    /**
     * The buyer is satisfied and wants the seller paid now rather than in a week.
     */
    public function release(StorePage $page, Request $request, string $token): RedirectResponse
    {
        $escrow = $this->findEscrow($page, $token);

        abort_if($escrow === null, 404);

        if (! $escrow->isHeld()) {
            return back()->with('status', $this->settledMessage($escrow));
        }

        app(EscrowService::class)->release($escrow, 'escrow_released_by_buyer');

        return back()->with('status', 'Released. '.config('app.name').' has paid out the seller.');
    }

    /**
     * Something is wrong. Freezes the money so an admin has to decide.
     *
     * A dispute is a claim, not a refund request: the buyer still does not know
     * the seller is the one at fault, and pretending otherwise would be a lie
     * about what the platform can see.
     */
    public function dispute(StorePage $page, Request $request, string $token): RedirectResponse
    {
        $escrow = $this->findEscrow($page, $token);

        abort_if($escrow === null, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        if (! $escrow->isHeld()) {
            return back()->withErrors([
                'reason' => $this->settledMessage($escrow),
            ]);
        }

        app(EscrowService::class)->raiseBuyerDispute($escrow, $data['reason']);

        return back()->with('status', 'Logged. The money is frozen until an admin reviews it.');
    }

    /**
     * The token alone decides which escrow this is; the store page must match,
     * so a link cannot be replayed against a different seller.
     */
    private function findEscrow(StorePage $page, string $token): ?Escrow
    {
        return Escrow::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->get()
            ->first(fn (Escrow $escrow) => hash_equals($escrow->releaseToken(), $token));
    }

    private function orderFor(Escrow $escrow): ?Order
    {
        return $escrow->payable_type === (new Order)->getMorphClass()
            ? Order::withoutGlobalScopes()->find($escrow->payable_id)
            : null;
    }

    private function settledMessage(Escrow $escrow): string
    {
        return match ($escrow->status) {
            EscrowStatus::Frozen => 'This payment is already under review.',
            EscrowStatus::Released => 'This payment has already been released to the seller.',
            EscrowStatus::Refunded => 'This payment has already been refunded to you.',
            default => 'This payment is no longer held.',
        };
    }
}
