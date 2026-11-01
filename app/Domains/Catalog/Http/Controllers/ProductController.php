<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Catalog\Actions\AdjustStock;
use App\Domains\Catalog\Enums\DeliveryMode;
use App\Domains\Catalog\Exceptions\OutOfStock;
use App\Domains\Catalog\Jobs\ProcessProductImage;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $page = $request->user()->storePages()->with('blocks')->firstOrFail();
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);
        $products = Product::query()
            ->with(['images'])
            ->where('store_page_id', $page->id)
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.products.index', compact('page', 'products'));
    }

    public function create(Request $request): View
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);
        $categories = Category::query()->where('store_page_id', $page->id)->orderBy('position')->get();

        return view('dashboard.products.form', ['page' => $page, 'categories' => $categories, 'product' => null]);
    }

    public function store(Request $request, SubscriptionEntitlements $entitlements): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);
        $entitlements->assertCanCreateListing($page);
        $data = $this->validated($request);
        $priceKobo = Money::toKobo($data['price']);
        unset($data['price']);
        $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : 'SKU-'.strtoupper(Str::random(8));
        $data['stock_qty'] = $data['stock_qty'] ?? 0;

        $product = Product::create($data + [
            'seller_id' => $request->user()->id,
            'store_page_id' => $page->id,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'price_kobo' => $priceKobo,
        ]);

        if ($request->hasFile('images')) {
            foreach (array_values($request->file('images')) as $i => $file) {
                $image = $product->images()->create(['path' => $file->store("products/{$product->id}", 'public'), 'position' => $i]);
                ProcessProductImage::dispatch($image->getKey());
            }
        }

        return redirect()
            ->route('products.variants.index', $product)
            ->with('status', "Product {$product->name} added. Add its variants below if it has size, colour or stock options.");
    }

    public function edit(Request $request, Product $product): View
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);
        abort_unless($product->store_page_id === $page->id, 403);
        $categories = Category::query()->where('store_page_id', $page->id)->orderBy('position')->get();

        return view('dashboard.products.form', [
            'page' => $page,
            'categories' => $categories,
            'product' => $product->load(['images', 'variants']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless($product->store_page_id === $page->id, 403);

        $data = $this->validated($request);
        $priceKobo = Money::toKobo($data['price']);
        unset($data['price']);
        $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : (filled($product->sku) ? $product->sku : 'SKU-'.strtoupper(Str::random(8)));

        $stockDelta = ($data['stock_qty'] ?? 0) - $product->stock_qty;
        unset($data['stock_qty']);

        $product->update($data + ['price_kobo' => $priceKobo]);

        if ($stockDelta !== 0) {
            app(AdjustStock::class)->handle($product->refresh(), $stockDelta, 'edited on product form');
        }

        if ($request->hasFile('images')) {
            foreach (array_values($request->file('images')) as $i => $file) {
                $image = $product->images()->create(['path' => $file->store("products/{$product->id}", 'public'), 'position' => $product->images()->count() + $i]);
                ProcessProductImage::dispatch($image->getKey());
            }
        }

        return redirect()
            ->route('products.edit', $product)
            ->with('status', "Product {$product->name} updated.");
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless($product->store_page_id === $page->id, 403);

        $data = $request->validate([
            'delta' => ['required', 'integer', 'min:-10000', 'max:10000'],
            'reason' => ['required', 'string', 'max:120'],
        ]);

        try {
            app(AdjustStock::class)->handle($product, $data['delta'], $data['reason']);
        } catch (OutOfStock) {
            return back()->withErrors(['stock' => "Not enough stock for {$product->name}."]);
        }

        return back()->with('status', "Stock for {$product->name} updated.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        Product::where('store_page_id', $page->id)->whereIn('id', $ids)->delete();

        return redirect()->route('products.index')->with('status', count($ids).' product(s) deleted.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless($product->store_page_id === $page->id, 403);

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Product deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at' => ['nullable', 'numeric', 'min:0'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date'],
            'sku' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:draft,active,archived'],
            'track_stock' => ['sometimes', 'boolean'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['sometimes', 'boolean'],
            'delivery_mode' => ['nullable', Rule::enum(DeliveryMode::class)],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'images.*' => ['nullable', 'image', 'max:4096'],
        ]);

        unset($data['price'], $data['images']);
        $data['track_stock'] = $request->boolean('track_stock');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['price'] = $request->input('price');
        // Blank means "inherit the store profile"; a typed fee is the override,
        // stored in kobo like every other price.
        $data['delivery_mode'] = filled($data['delivery_mode'] ?? null) ? $data['delivery_mode'] : null;
        $data['delivery_fee_kobo'] = filled($data['delivery_fee'] ?? null)
            ? Money::toKobo($data['delivery_fee'])
            : null;
        unset($data['delivery_fee']);

        $priceKobo = Money::toKobo($data['price']);
        $compareAtKobo = filled($data['compare_at'] ?? null)
            ? Money::toKobo($data['compare_at'])
            : null;
        $saleStartsAt = filled($data['sale_starts_at'] ?? null)
            ? Carbon::parse($data['sale_starts_at'])
            : null;
        $saleEndsAt = filled($data['sale_ends_at'] ?? null)
            ? Carbon::parse($data['sale_ends_at'])
            : null;

        if ($compareAtKobo !== null && $compareAtKobo <= $priceKobo) {
            throw ValidationException::withMessages([
                'compare_at' => 'The original price must be higher than the price the buyer pays.',
            ]);
        }

        if ($saleStartsAt !== null && $saleEndsAt !== null && $saleEndsAt->lessThanOrEqualTo($saleStartsAt)) {
            throw ValidationException::withMessages([
                'sale_ends_at' => 'The sale must end after it starts.',
            ]);
        }

        // Dates without an original price have nothing to compare against.
        if ($compareAtKobo === null) {
            $saleStartsAt = null;
            $saleEndsAt = null;
        }

        unset($data['compare_at'], $data['sale_starts_at'], $data['sale_ends_at']);
        $data['compare_at_kobo'] = $compareAtKobo;
        $data['sale_starts_at'] = $saleStartsAt;
        $data['sale_ends_at'] = $saleEndsAt;

        return $data;
    }
}
