<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Shared\Support\Money;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    public function index(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        return view('dashboard.variants.index', [
            'product' => $product,
            'variants' => $product->variants()->with('product')->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        return view('dashboard.variants.form', [
            'product' => $product,
            'variant' => null,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $data = $this->validated($request, $product);
        $variant = $product->variants()->create($data + [
            'store_page_id' => $product->store_page_id,
            'price_kobo' => filled($data['price'] ?? null) ? Money::toKobo($data['price']) : null,
            'sku' => filled($data['sku'] ?? null) ? $data['sku'] : ProductVariant::generateSku($product->store_page_id),
        ]);
        $this->storeImage($request, $variant);

        return redirect()
            ->route('products.variants.index', $product)
            ->with('status', "Variant {$variant->name} added.");
    }

    public function edit(Request $request, ProductVariant $variant): View
    {
        $product = $this->authorizeVariant($request, $variant);

        return view('dashboard.variants.form', [
            'product' => $product,
            'variant' => $variant,
        ]);
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $product = $this->authorizeVariant($request, $variant->fresh());

        $data = $this->validated($request, $product);
        $data['price_kobo'] = filled($data['price'] ?? null) ? Money::toKobo($data['price']) : null;

        $variant->update($data);

        $this->storeImage($request, $variant);

        return redirect()
            ->route('products.variants.index', $product)
            ->with('status', "Variant {$variant->name} saved.");
    }

    public function destroy(Request $request, ProductVariant $variant): RedirectResponse
    {
        $variant = $variant->fresh();

        $this->authorizeVariant($request, $variant);

        if ($variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
        }

        $variant->delete();

        return back()->with('status', 'Variant deleted.');
    }

    private function validated(Request $request, Product $product): array
    {
        $variant = $request->route('variant');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'sku' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail) use ($variant, $product) {
                    if (filled($value) && ProductVariant::isSkuTaken($value, $product->store_page_id, $variant?->id)) {
                        $fail('That SKU is already used by another variant.');
                    }
                },
            ],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        unset($data['image']);

        $data['options'] = ['label' => $data['name']];
        $data['stock_qty'] = $data['stock_qty'] ?? 0;

        return $data;
    }

    private function storeImage(Request $request, ProductVariant $variant): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        if ($variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
        }

        $variant->update([
            'image_path' => $request->file('image')->store("variants/{$variant->product_id}", 'public'),
        ]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless($product->store_page_id === $page->id, 403);
    }

    /**
     * Ensure the variant belongs to the seller's own store page, returning its product.
     */
    private function authorizeVariant(Request $request, ProductVariant $variant): Product
    {
        $pageId = $request->user()->storePages()->value('id');

        $product = $variant->product;

        abort_unless($pageId && $product && $product->store_page_id === $pageId, 404);

        return $product;
    }
}
