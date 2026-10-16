<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Seller\Actions\PublishStorePage;
use App\Domains\Seller\Actions\UnpublishStorePage;
use App\Domains\Seller\Exceptions\NotReadyToPublish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StorePageController extends Controller
{
    public function publish(
        Request $request,
        PublishStorePage $publishStorePage,
    ): RedirectResponse {
        $page = $request->user()->storePages()->firstOrFail();

        try {
            $publishStorePage->handle($page);
        } catch (NotReadyToPublish $exception) {
            // Flashed under their own key rather than through withErrors(), which
            // only carries strings, so the dashboard can render each blocker with
            // its own fix-it link instead of one mashed-together message.
            return redirect()
                ->route('dashboard')
                ->with('publish_blockers', $exception->blockers);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', "{$page->name} is live. Share your link to start selling.");
    }

    public function unpublish(Request $request, UnpublishStorePage $unpublishStorePage): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        $unpublishStorePage->handle($page);

        return back()->with('status', "{$page->name} is back to draft. Your orders and customers are untouched.");
    }
}
