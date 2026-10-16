<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Actions\ModerateReview;
use App\Domains\Catalog\Models\Review;
use App\Domains\Seller\Enums\ReviewStatus;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The seller's review inbox.
 *
 * Reviews arrive from buyers who have no account, so every one of them is
 * unmoderated text written by a stranger, about to appear on a page the seller is
 * responsible for. This screen is the gate, and two rules make the feature safe
 * to ship at all:
 *
 *  - Nothing renders until approved. Not the text, not the rating, not the count.
 *  - The score is recalculated on every status change, including declining one.
 *    A rating that only ever climbs is the one number nobody should trust.
 *
 * Lives in the Catalog domain because the table and the aggregate logic are
 * catalog's; it is mounted on dashboard routes because the person who acts on it
 * is the seller.
 *
 * Ownership is resolved from the signed-in seller's own page rather than from a
 * route parameter. There is no `{page}` on a dashboard URL to bind, and binding
 * one would let a seller reach another seller's queue by editing the path.
 */
class ReviewInboxController extends Controller
{
    public function index(Request $request): View
    {
        $page = $this->page($request);

        $status = ReviewStatus::tryFrom((string) $request->query('status'))
            ?? ReviewStatus::Pending;

        $reviews = $page->reviews()
            ->where('status', $status->value)
            // The reviewable is loaded for its name. The query itself is already
            // seller-scoped by the BelongsToSeller global scope on Review.
            ->with('reviewable')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.reviews.index', [
            'page' => $page,
            'reviews' => $reviews,
            'status' => $status,
            'statuses' => ReviewStatus::cases(),
            'counts' => $this->counts($page),
        ]);
    }

    public function approve(Request $request, Review $review, ModerateReview $moderate): RedirectResponse
    {
        $this->authorise($request, $review);

        $moderate->approve($review);

        return back()->with('status', 'Published. It is on the page now.');
    }

    public function reject(Request $request, Review $review, ModerateReview $moderate): RedirectResponse
    {
        $this->authorise($request, $review);

        $moderate->reject($review);

        return back()->with('status', 'Declined. Your score has been recalculated.');
    }

    /**
     * Pinned to the top of the list, or not.
     *
     * Separate from approval on purpose: a seller should be able to stop putting
     * a review first without un-publishing it.
     */
    public function feature(Request $request, Review $review): RedirectResponse
    {
        $this->authorise($request, $review);

        $review->update(['is_featured' => ! $review->is_featured]);

        return back()->with('status', $review->is_featured
            ? 'Pinned to the top of the list.'
            : 'Unpinned.');
    }

    /**
     * How many sit in each queue, so a tab carries its own number instead of
     * making the seller click it to find out whether anything is waiting.
     *
     * @return array<string, int>
     */
    private function counts(StorePage $page): array
    {
        $counts = $page->reviews()
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (ReviewStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    private function page(Request $request): StorePage
    {
        return $request->user()->storePages()->firstOrFail();
    }

    /**
     * Refuse a review belonging to somebody else's page.
     *
     * 404 rather than 403: a seller who guessed an id should not be able to learn
     * that it exists.
     */
    private function authorise(Request $request, Review $review): void
    {
        abort_unless($review->store_page_id === $this->page($request)->id, 404);
    }
}
