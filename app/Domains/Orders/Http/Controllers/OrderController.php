<?php

namespace App\Domains\Orders\Http\Controllers;

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
            'order' => $order->load(['items.product', 'items.variant', 'history']),
        ]);
    }

    public function updateStatus(Request $request, Order $order, UpdateOrderStatus $updateOrderStatus): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        abort_unless($order->store_page_id === $page->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $status = OrderStatus::from($data['status']);

        abort_if(
            $order->status === OrderStatus::PendingPayment && $status === OrderStatus::Paid,
            422,
            'An unpaid checkout cannot be marked paid by hand. The payment provider confirms it.',
        );

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
