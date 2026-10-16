<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Catalog\Models\Category;
use App\Domains\Directory\Services\DirectoryQuery;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The public directory: every published page, searchable and filterable.
 *
 * The one rule this screen lives by is that only published pages appear. Drafts
 * are work in progress and suspended pages are under dispute; leaking either into
 * a directory is how a seller finds out about a suspension from a stranger
 * rather than from us. That is enforced in DirectoryQuery so no controller can
 * forget it.
 */
class DirectoryController extends Controller
{
    public function index(Request $request, DirectoryQuery $query): View
    {
        return view('pages.directory.index', [
            'pages' => $query->paginate($request),
            'themes' => Theme::cases(),
            'states' => $query->states(),
            'categories' => $query->categories(),
            'filters' => $request->only(['q', 'theme', 'category', 'state', 'city']),
            'total' => $query->total(),
        ]);
    }

    /**
     * A clean, indexable URL per filter combination.
     *
     * /directory/lagos/hair-salons stays useful in a search engine and in a
     * WhatsApp message, where a query string is easy to lose.
     */
    public function area(Request $request, DirectoryQuery $query, string $state, ?string $category = null): View
    {
        $state = $query->stateSlug($state) ?? $state;

        return view('pages.directory.index', [
            'pages' => $query->paginate($request->merge(['state' => $state, 'category' => $category])),
            'themes' => Theme::cases(),
            'states' => $query->states(),
            'categories' => $query->categories(),
            'filters' => ['q' => $request->query('q'), 'state' => $state, 'category' => $category],
            'total' => $query->total(),
            'heading' => $category === null
                ? 'Pages in '.ucfirst(str_replace('-', ' ', $state))
                : 'Pages in '.ucfirst(str_replace('-', ' ', $state)),
            'subheading' => $category === null ? null : $this->categoryName($category),
        ]);
    }

    private function categoryName(string $slug): ?string
    {
        return Category::query()->where('slug', $slug)->value('name');
    }
}
