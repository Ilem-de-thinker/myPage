<?php

namespace App\Domains\Customers\Http\Controllers;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerNote;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * The customer book. Rows are built by the orders buyers place, so this is
     * read-mostly: sellers search it, note things down and manage consent.
     */
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->with('storePage')
            ->search($request->query('q'))
            ->orderByDesc('last_activity_at')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.customers.index', [
            'page' => $this->page($request),
            'customers' => $customers,
            'search' => $request->query('q'),
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $page = $this->page($request);

        abort_unless($customer->seller_id === $page->seller_id, 404);

        return view('dashboard.customers.show', [
            'page' => $page,
            'customer' => $customer->load(['storePage', 'notes', 'orders.items']),
        ]);
    }

    public function storeNote(Request $request, Customer $customer): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($customer->seller_id === $page->seller_id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $customer->notes()->create([
            'seller_id' => $page->seller_id,
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Note saved.');
    }

    public function destroyNote(Request $request, Customer $customer, CustomerNote $note): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($customer->seller_id === $page->seller_id, 404);
        abort_unless($note->customer_id === $customer->getKey(), 404);

        $note->delete();

        return back()->with('status', 'Note deleted.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($customer->seller_id === $page->seller_id, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            // Consent is a marketing decision the seller makes explicitly, so it
            // is a checkbox in a form and never implied by an order.
            'opted_in' => ['nullable', 'boolean'],
        ]);

        $customer->update([
            'name' => $data['name'] ?: $customer->name,
            'phone' => PhoneNumber::normalize($data['phone'] ?? null) ?: $customer->phone,
            'opted_in' => $request->boolean('opted_in'),
        ]);

        return back()->with('status', "{$customer->displayName()} updated.");
    }

    private function page(Request $request): StorePage
    {
        /** @var Seller $seller */
        $seller = $request->user();

        return $seller->storePages()->firstOrFail();
    }
}
