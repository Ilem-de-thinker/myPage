<?php

namespace App\Domains\Orders\Http\Controllers;

use App\Domains\Billing\Actions\EscrowService;
use App\Domains\Orders\Actions\UpdateOrderStatus;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * The inbox. WhatsApp and on-platform orders are the same table, split into
     * two tabs by source.
     */
    public function index(Request $request): View
    {
        $page = $request->user()->storePages()->firstOrFail();

        $source = $this->sourceFromRequest($request);

        $orders = Order::query()
            ->where('store_page_id', $page->id)
            ->when($source, fn ($query, $source) => $query->where('source', $source->value))
            ->with(['items'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.orders.index', [
            'page' => $page,
            'orders' => $orders,
            'activeSource' => $source,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($order->store_page_id === $page->id, 404);

        return view('dashboard.orders.show', [
            'page' => $page,
            'order' => $order->load(['items.product', 'items.variant', 'history', 'escrow']),
        ]);
    }

    /**
     * The seller's half of escrow: raise a dispute and freeze the buyer's money.
     *
     * Deliberately one-way from here. A seller who freezes an order cannot also
     * push it to released or refunded — an admin decides where held money goes,
     * because the alternative is a seller resolving their own complaint.
     */
    public function disputeEscrow(Request $request, Order $order, EscrowService $escrows): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($order->store_page_id === $page->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        $escrow = $order->escrow;

        if ($escrow === null) {
            return back()->withErrors(['reason' => 'This order is not using escrow.']);
        }

        if (! $escrows->freeze($escrow, $request->user(), $data['reason'])) {
            return back()->withErrors([
                'reason' => 'That payment has already moved. It cannot be frozen now.',
            ]);
        }

        return back()->with('status', 'Frozen. An admin decides where this money goes.');
    }

    public function updateStatus(Request $request, Order $order, UpdateOrderStatus $updateOrderStatus): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($order->store_page_id === $page->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $status = OrderStatus::from($data['status']);

        abort_unless($order->status->canMoveTo($status), 422, "An order cannot move from {$order->status->value} to {$status->value}.");

        $updateOrderStatus->handle($order, $status, $request->user());

        return back()->with('status', "Order {$order->reference} marked as {$status->label()}.");
    }

    private function sourceFromRequest(Request $request): ?OrderSource
    {
        if (! $request->filled('source')) {
            return null;
        }

        return OrderSource::tryFrom((string) $request->query('source'));
    }
}
