<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Review;
use App\Domains\Seller\Enums\ReviewStatus;

/**
 * Approves or declines a review, and keeps the score honest.
 *
 * The recalculation is the point of this class. A rating that drifts from the
 * rows behind it is worse than no rating at all, because it is the one number on
 * the card a buyer trusts without checking — so it is recalculated on *every*
 * status change, including declining one. Approving without recalculating leaves
 * a score that only ever climbs, which is exactly the number nobody should trust.
 */
class ModerateReview
{
    public function approve(Review $review): Review
    {
        $review->update([
            'status' => ReviewStatus::Approved,
            // Stamped now, not on submission: "newest first" has to mean newest
            // *published*, or a seller's queue works in reverse.
            'published_at' => $review->published_at ?? now(),
        ]);

        $review->reviewable?->recalculateRating();

        return $review;
    }

    public function reject(Review $review): Review
    {
        // Declining also clears any pin: a featured review that is no longer
        // published cannot stay at the top of a list it is not part of.
        $review->update(['status' => ReviewStatus::Rejected, 'is_featured' => false]);

        $review->reviewable?->recalculateRating();

        return $review;
    }
}
