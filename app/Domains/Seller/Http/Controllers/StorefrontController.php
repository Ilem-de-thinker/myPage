<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Actions\GenerateWhatsAppOrderLink;
use App\Domains\Orders\Exceptions\InvalidCart;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function show(StorePage $page): View
    {
        return view("themes.{$page->theme->value}.page", [
            'page' => $page,
            'products' => $page->theme === Theme::Store
                ? Product::query()->with('images')->active()->where('store_page_id', $page->id)->latest()->limit(8)->get()
                : null,
        ]);
    }

    public function about(StorePage $page): View
    {
        return view("themes.{$page->theme->value}.about", ['page' => $page]);
    }

    public function contact(StorePage $page): View
    {
        return view("themes.{$page->theme->value}.contact", ['page' => $page]);
    }

    public function products(StorePage $page, Request $request): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        $categories = Category::query()->where('store_page_id', $page->id)->orderBy('position')->get();

        $products = Product::query()
            ->with('images')
            ->active()
            ->where('store_page_id', $page->id)
            ->when($request->integer('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->when($request->string('q')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('themes.store.products', compact('page', 'products', 'categories'));
    }

    public function productShow(StorePage $page, string $productSlug): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        $product = Product::query()
            ->with(['images', 'category'])
            ->active()
            ->where('store_page_id', $page->id)
            ->where('slug', $productSlug)
            ->firstOrFail();

        $related = Product::query()
            ->with('images')
            ->active()
            ->where('store_page_id', $page->id)
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->latest()
            ->limit(4)
            ->get();

        return view('themes.store.product-show', compact('page', 'product', 'related'));
    }

    public function services(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        return view('themes.service.services', ['page' => $page]);
    }

    public function serviceShow(StorePage $page, string $serviceSlug): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        return view('themes.service.service-show', ['page' => $page]);
    }

    public function cart(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        return view('themes.store.cart', ['page' => $page]);
    }

    public function checkout(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        return view('themes.store.checkout', ['page' => $page]);
    }

    public function book(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        return view('themes.service.booking', ['page' => $page]);
    }

    public function whatsappOrder(StorePage $page, Request $request, GenerateWhatsAppOrderLink $whatsappOrder): JsonResponse
    {
        abort_unless($page->theme === Theme::Store, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $cart = array_map(fn (array $item) => [
            'product_id' => $item['id'],
            'quantity' => $item['qty'],
        ], $data['items']);

        try {
            // The order is saved before the buyer is sent to WhatsApp so the seller
            // has a pending record with the same reference the buyer quotes.
            $result = $whatsappOrder->handle($page, $cart, [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
        } catch (InvalidCart $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json([
            'reference' => $result['reference'],
            'total_kobo' => $result['total_kobo'],
            'total' => $result['total'],
            'url' => $result['url'],
        ]);
    }

    public function trackOrder(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        return view('themes.store.order-tracking', ['page' => $page]);
    }
}
