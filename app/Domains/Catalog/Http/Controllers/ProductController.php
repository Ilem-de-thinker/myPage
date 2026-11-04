<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiLimits;
use App\Domains\Ai\Services\AiUsageReservations;
use App\Domains\Billing\Services\SubscriptionEntitlements;
use App\Domains\Catalog\Actions\AdjustStock;
use App\Domains\Catalog\Enums\DeliveryMode;
use App\Domains\Catalog\Exceptions\OutOfStock;
use App\Domains\Catalog\Jobs\ProcessProductImage;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    /** A product gallery longer than this is not a catalogue, it is a dump. */
    private const MAX_IMAGES = 8;

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

        // The entitlement check and the insert share one transaction so the
        // subscription lock is still held while the new row lands. The limit is
        // checked before validation so an over-limit seller sees the upgrade
        // message, not a field error.
        $product = DB::transaction(function () use ($entitlements, $page, $request): Product {
            $entitlements->assertCanCreateListing($page);

            $data = $this->validated($request, $page);
            $priceKobo = Money::toKobo($data['price']);
            unset($data['price']);
            $data['sku'] = filled($data['sku'] ?? null) ? $data['sku'] : 'SKU-'.strtoupper(Str::random(8));
            $data['stock_qty'] = $data['stock_qty'] ?? 0;

            return Product::create($data + [
                'seller_id' => $request->user()->id,
                'store_page_id' => $page->id,
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
                'price_kobo' => $priceKobo,
            ]);
        });

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

        $data = $this->validated($request, $page);
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
            $incoming = count($request->file('images'));
            $existing = $product->images()->count();

            if ($existing + $incoming > self::MAX_IMAGES) {
                throw ValidationException::withMessages([
                    'images' => 'A product can have up to '.self::MAX_IMAGES.' photos. Remove one before adding more.',
                ]);
            }

            foreach (array_values($request->file('images')) as $i => $file) {
                $image = $product->images()->create(['path' => $file->store("products/{$product->id}", 'public'), 'position' => $existing + $i]);
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

        $doomed = Product::where('store_page_id', $page->id)->whereIn('id', $ids)->pluck('id');
        // Soft delete on purpose: the row and its images stay recoverable for
        // 30 days, then the purge job removes both for good.
        Product::where('store_page_id', $page->id)->whereIn('id', $doomed)->delete();

        return redirect()->route('products.index')->with('status', $doomed->count().' product(s) deleted. You can restore them for 30 days.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless($product->store_page_id === $page->id, 403);

        $product->delete();

        return redirect()->route('products.index')->with('status', 'Product deleted. You can restore it for 30 days.');
    }

    public function generateDescriptions(Request $request): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'tone' => ['nullable', Rule::in(['professional', 'friendly', 'playful', 'luxury', 'concise'])],
            'length' => ['nullable', Rule::in(['short', 'medium', 'long'])],
            'language' => ['nullable', Rule::in(['English', 'Nigerian Pidgin', 'Yoruba', 'Igbo', 'Hausa'])],
            'overwrite' => ['sometimes', 'boolean'],
        ]);
        $ids = $data['ids'];
        $tone = $data['tone'] ?? 'professional';
        $length = $data['length'] ?? 'medium';
        $language = $data['language'] ?? 'English';
        $overwrite = $request->boolean('overwrite');

        $limits = app(AiLimits::class);
        $credits = app(AiCreditService::class);
        $limits->throttle($page);
        $limits->assertWithinTaskCap(count($ids));
        $limits->assertCanQueue($page, 1);
        $estimate = $credits->estimate($page, count($ids), 'description_generation');
        $slots = $limits->availableSlots($page);
        abort_if($slots < 1 || $estimate['allowed'] < 1, 429, 'This store has reached its monthly Mercho limit for descriptions.');

        $allowed = min($estimate['allowed'], $slots, $limits->maxBatchItems());
        $ids = array_slice($ids, 0, $allowed);

        $products = Product::where('store_page_id', $page->id)->whereIn('id', $ids)->with('category')->orderBy('id')->get();
        abort_if($products->isEmpty(), 422, 'Select at least one product from this store.');

        $task = AiTask::create(['seller_id' => $page->seller_id, 'store_page_id' => $page->id, 'type' => 'descriptions', 'status' => 'queued', 'total' => $products->count()]);

        $queued = 0;

        foreach ($products as $product) {
            $operation = AiOperation::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->id,
                'ai_task_id' => $task->id,
                'task' => 'description_generation',
                'status' => 'queued',
                'input' => [
                    'feature' => 'descriptions',
                    '_no_draft' => true,
                    'item' => ['name' => $product->name, 'category' => $product->category?->name, 'price' => $product->price_kobo / 100, 'attributes' => $product->ai_metadata],
                    'options' => ['tone' => $tone, 'length' => $length, 'language' => $language, 'overwrite' => $overwrite],
                    'product_id' => $product->id,
                ],
            ]);

            try {
                app(AiUsageReservations::class)->reserveAndDispatch($operation);
            } catch (Throwable) {
                // The reservation is authoritative and may fail when a
                // concurrent batch took the last slot or credit. Keep the
                // operations already queued; the reservation helper has
                // already failed this one.
                break;
            }

            $queued++;
        }

        $task->update([
            'status' => $queued > 0 ? 'processing' : 'failed',
            'total' => $queued,
            'started_at' => $queued > 0 ? now() : null,
        ]);

        return redirect()->route('dashboard.ai.tasks.show', $task)->with('status', 'Generating descriptions in the background.');
    }

    public function applyDescriptions(Request $request, int $task): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $taskModel = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $previous = [];
        $previousMetadata = [];
        foreach ($taskModel->operations()->where('status', 'succeeded')->get() as $op) {
            $productId = $op->input['product_id'] ?? null;
            $product = $productId ? Product::where('store_page_id', $page->id)->find($productId) : null;
            if (! $product) {
                continue;
            }

            $output = $op->output ?? [];
            $overwrite = $op->input['options']['overwrite'] ?? false;
            if (! $overwrite && filled($product->description)) {
                continue;
            }
            if (blank($output['description'] ?? null)) {
                continue;
            }

            $previous[$product->id] = $product->description;
            $previousMetadata[$product->id] = $product->ai_metadata;
            $metadata = $product->ai_metadata ?? [];
            $metadata['seo'] = [
                'title' => $output['seo_title'] ?? ($metadata['seo']['title'] ?? null),
                'description' => $output['seo_description'] ?? ($metadata['seo']['description'] ?? null),
            ];
            $metadata['ai_description'] = [
                'insufficient_info' => $output['insufficient_info'] ?? null,
                'flags' => array_values((array) ($output['flags'] ?? [])),
            ];
            $product->update([
                'description' => $output['description'],
                'ai_metadata' => $metadata,
            ]);
        }

        $taskModel->update([
            'summary' => array_merge($taskModel->summary ?? [], [
                'previous_descriptions' => $previous,
                'previous_ai_metadata' => $previousMetadata,
            ]),
        ]);

        return redirect()->route('products.index')->with('status', 'Descriptions applied. Review the products, or undo from the task page.');
    }

    public function undoDescriptions(Request $request, int $task): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $taskModel = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $previous = $taskModel->summary['previous_descriptions'] ?? [];
        $previousMetadata = $taskModel->summary['previous_ai_metadata'] ?? [];

        foreach ($previous as $productId => $description) {
            Product::where('store_page_id', $page->id)->whereKey($productId)->update([
                'description' => $description,
                'ai_metadata' => $previousMetadata[$productId] ?? null,
            ]);
        }

        $taskModel->update(['summary' => array_merge($taskModel->summary ?? [], [
            'previous_descriptions' => [],
            'previous_ai_metadata' => [],
        ])]);

        return redirect()->route('products.index')->with('status', 'Descriptions restored.');
    }

    private function validated(Request $request, StorePage $page): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('store_page_id', $page->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'compare_at' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date'],
            'sku' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:draft,active,archived'],
            'track_stock' => ['sometimes', 'boolean'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['sometimes', 'boolean'],
            'delivery_mode' => ['nullable', Rule::enum(DeliveryMode::class)],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
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
