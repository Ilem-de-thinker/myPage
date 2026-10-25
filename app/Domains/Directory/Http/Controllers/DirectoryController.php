<?php

namespace App\Domains\Directory\Http\Controllers;

use App\Domains\Directory\Services\DirectoryQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
    public function area(Request $request, DirectoryQuery $query, string $state, ?string $category = null): RedirectResponse
    {
        $state = $query->stateSlug($state) ?? $state;

        $params = array_filter([
            'state' => $state,
            'category' => $category,
            'q' => $request->query('q'),
        ]);

        return redirect()
            ->route($category === null ? 'directory.stores' : 'directory.products', $params)
            ->setStatusCode(301);
    }
}
