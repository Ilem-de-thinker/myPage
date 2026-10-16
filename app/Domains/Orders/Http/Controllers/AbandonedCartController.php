<?php

namespace App\Domains\Orders\Http\Controllers;

use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AbandonedCartController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);

        $status = AbandonedCartStatus::tryFrom((string) $request->query('status')) ?? AbandonedCartStatus::Open;

        $carts = AbandonedCart::query()
            ->where('store_page_id', $page->id)
            ->where('status', $status->value)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = AbandonedCart::query()
            ->where('store_page_id', $page->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard.abandoned-carts.index', [
            'page' => $page,
            'carts' => $carts,
            'activeStatus' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * A seller may write the buyer off by hand, for instance after reaching them
     * on WhatsApp, or because the cart was never going to convert.
     */
    public function destroy(Request $request, AbandonedCart $cart): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($cart->store_page_id === $page->id, 404);

        $cart->update(['status' => AbandonedCartStatus::Recovered]);

        return back()->with('status', 'Cart closed. We will stop chasing that buyer.');
    }

    private function page(Request $request): StorePage
    {
        return $request->user()->storePages()->firstOrFail();
    }
}
