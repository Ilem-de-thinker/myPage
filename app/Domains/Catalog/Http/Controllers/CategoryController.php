<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiLimits;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);

        $task = null;
        $proposal = null;
        if ($request->filled('task')) {
            $task = AiTask::query()->where('store_page_id', $page->id)->with('operations')->find($request->integer('task'));
            $operation = $task?->operations()->where('status', 'succeeded')->latest()->first();
            $proposal = $operation?->output;
        }

        $categories = $page->categories()
            ->withCount('products')
            ->when(
                $request->string('q')->isNotEmpty(),
                fn ($query) => $query->where('name', 'like', '%'.$request->input('q').'%'),
            )
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.categories.index', [
            'page' => $page,
            'categories' => $categories,
            'task' => $task,
            'proposal' => $proposal,
        ]);
    }

    public function create(Request $request): View
    {
        return view('dashboard.categories.form', [
            'page' => $this->page($request),
            'category' => null,
        ]);
    }

    /**
     * Answers two callers: the category form redirects, while the product form
     * creates a category inline and expects JSON back.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $page = $this->page($request);

        $data = $this->validated($request, $page);

        $category = $page->categories()->create([
            'seller_id' => $page->seller_id,
            'name' => $data['name'],
            'position' => $page->categories()->count(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name], 201);
        }

        return redirect()
            ->route('categories.index')
            ->with('status', "Category {$category->name} added.");
    }

    public function edit(Request $request, Category $category): View
    {
        $page = $this->page($request);

        abort_unless($category->store_page_id === $page->id, 404);

        return view('dashboard.categories.form', [
            'page' => $page,
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($category->store_page_id === $page->id, 404);

        $data = $this->validated($request, $page);

        $category->update([
            'name' => $data['name'],
            'slug' => $category->name === $data['name']
                ? $category->slug
                : Category::generateSlug($data['name']),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('status', "Category {$category->name} saved.");
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $page = $this->page($request);

        abort_unless($category->store_page_id === $page->id, 404);

        // Products stay in the catalogue; they just become uncategorised.
        $category->products()->update(['category_id' => null]);

        $name = $category->name;
        $category->delete();

        return back()->with('status', "Category {$name} deleted. Its products were kept.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $page = $this->page($request);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        // Scoped to this store page, so ids from another seller are ignored
        // rather than deleted.
        $categories = Category::where('store_page_id', $page->id)->whereIn('id', $ids)->get();

        $categories->each(function (Category $category) {
            $category->products()->update(['category_id' => null]);
            $category->delete();
        });

        return redirect()
            ->route('categories.index')
            ->with('status', $categories->count().' categor'.($categories->count() === 1 ? 'y' : 'ies').' deleted. Products were kept.');
    }

    public function generateWithAi(Request $request): RedirectResponse
    {
        $page = $this->page($request);
        abort_unless(in_array($page->businessType(), ['store', 'both'], true), 404);

        $limits = app(AiLimits::class);
        $credits = app(AiCreditService::class);
        $limits->throttle($page);
        $limits->assertCanQueue($page, 1);
        $estimate = $credits->estimate($page, 1, 'category_structure');
        abort_if($estimate['allowed'] < 1, 429, 'This store has reached its monthly Mercho limit for categories.');

        $products = $page->products()->with('category')->orderBy('id')->get();
        abort_if($products->isEmpty(), 422, 'Add a few products before Mercho can suggest categories.');

        $items = $products->map(fn (Product $product): array => [
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category?->name,
        ])->values()->all();

        $task = AiTask::create(['seller_id' => $page->seller_id, 'store_page_id' => $page->id, 'type' => 'categories', 'status' => 'queued', 'total' => 1]);
        $operation = AiOperation::create([
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->id,
            'ai_task_id' => $task->id,
            'task' => 'category_structure',
            'status' => 'queued',
            'input' => [
                'feature' => 'categories',
                '_no_draft' => true,
                'items' => $items,
                'item_ids' => $products->pluck('id')->all(),
                'existing_categories' => $page->categories()->pluck('name')->all(),
                'business_type' => $page->businessType(),
            ],
        ]);
        $task->update(['status' => 'processing', 'started_at' => now()]);
        RunAiOperation::dispatch($operation);

        return redirect()->route('categories.index', ['task' => $task->id])->with('status', 'Generating categories in the background.');
    }

    public function applyGeneratedCategories(Request $request, int $task): RedirectResponse
    {
        $page = $this->page($request);
        $taskModel = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $operation = $taskModel->operations()->where('status', 'succeeded')->latest()->first();
        abort_unless($operation, 404);

        $output = $operation->output ?? [];
        $previous = $page->products()->pluck('category_id', 'id')->all();

        $existing = $page->categories()->get()->keyBy(fn (Category $category): string => mb_strtolower($category->name));
        $categoryMap = [];
        $createdIds = [];
        foreach (($output['categories'] ?? []) as $category) {
            $name = trim((string) ($category['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            $model = $existing->get($key);
            if (! $model) {
                $model = $page->categories()->create([
                    'seller_id' => $page->seller_id,
                    'name' => $name,
                    'position' => $page->categories()->count(),
                ]);
                $createdIds[] = $model->id;
                $existing->put($key, $model);
            }
            $categoryMap[$key] = $model->id;
        }

        $itemIds = array_values(array_filter((array) ($operation->input['item_ids'] ?? []), 'is_numeric'));
        $products = $page->products()->orderBy('id')->get()->values();
        $productsById = $products->keyBy('id');

        foreach (($output['assignments'] ?? []) as $assignment) {
            $index = (int) ($assignment['item_index'] ?? -1);
            $product = isset($itemIds[$index])
                ? $productsById->get((int) $itemIds[$index])
                : $products->get($index);
            if (! $product) {
                continue;
            }

            $name = trim((string) ($assignment['category_name'] ?? ''));
            $key = mb_strtolower($name);
            if ($key !== '' && ! isset($categoryMap[$key])) {
                $model = $existing->get($key) ?: $page->categories()->create([
                    'seller_id' => $page->seller_id,
                    'name' => $name,
                    'position' => $page->categories()->count(),
                ]);
                if (! $existing->has($key)) {
                    $createdIds[] = $model->id;
                    $existing->put($key, $model);
                }
                $categoryMap[$key] = $model->id;
            }

            $product->update(['category_id' => $categoryMap[$key] ?? null]);
        }

        $taskModel->update([
            'summary' => array_merge($taskModel->summary ?? [], [
                'previous_assignments' => $previous,
                'created_category_ids' => array_values(array_unique($createdIds)),
            ]),
        ]);

        return redirect()
            ->route('categories.index', ['task' => $taskModel->id])
            ->with('status', 'Categories applied. Review the result, or undo if it is not right.');
    }

    public function undoGeneratedCategories(Request $request, int $task): RedirectResponse
    {
        $page = $this->page($request);
        $taskModel = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $previous = $taskModel->summary['previous_assignments'] ?? [];

        foreach ($previous as $productId => $categoryId) {
            Product::where('store_page_id', $page->id)->whereKey($productId)->update(['category_id' => $categoryId]);
        }

        $createdIds = array_values(array_filter((array) ($taskModel->summary['created_category_ids'] ?? []), 'is_numeric'));
        if ($createdIds !== []) {
            Category::query()
                ->where('store_page_id', $page->id)
                ->whereIn('id', $createdIds)
                ->whereDoesntHave('products')
                ->delete();
        }

        $taskModel->update(['summary' => array_merge($taskModel->summary ?? [], [
            'previous_assignments' => [],
            'created_category_ids' => [],
        ])]);

        return redirect()->route('categories.index')->with('status', 'Category assignments restored.');
    }

    private function page(Request $request): StorePage
    {
        return $request->user()->storePages()->firstOrFail();
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, StorePage $page): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')->where('store_page_id', $page->id),
            ],
        ]);
    }
}
