<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Models\Category;
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
