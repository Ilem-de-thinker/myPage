<?php

namespace App\Shared\Traits;

use App\Domains\Catalog\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * A cached rating and review count on a reviewable row.
 *
 * Shared by StorePage, Product and Service because all three are reviewed and all
 * three show a star rating, and because the one rule that matters — a count must
 * only ever contain approved reviews — has to hold identically on every one of
 * them. Three hand-written recalculators would drift, and the first one to drift
 * would be the one nobody looked at.
 *
 * The count is a column rather than a COUNT query per row. A catalogue grid of
 * twelve products would otherwise issue thirteen queries to draw a screen of
 * stars, and the answer only changes when a review is approved: an event, not a
 * per-request question.
 */
trait HasRating
{
    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Whether this row has ever been rated.
     *
     * A rating with no reviews behind it is a zero that would otherwise render as
     * "0.0 out of 5" on a page nobody has reviewed yet.
     */
    public function hasRating(): bool
    {
        return $this->review_count > 0 && $this->rating > 0;
    }

    /**
     * The score rounded to the half a reader would actually type.
     *
     * A stored 4.37 has to render as 4.5 or it reads as noise. The count travels
     * with it on every card: one five-star review is not a consensus, and a bare
     * 5.0 claims that it is.
     */
    public function ratingLabel(): float
    {
        return round(($this->rating ?? 0) * 2) / 2;
    }

    /**
     * Recompute rating and review_count from the approved rows.
     *
     * Called whenever approval changes, never on read. It is an UPDATE on the
     * row itself rather than a model save so it cannot clobber a price a seller
     * is editing at the same moment — the seller inbox and the product editor are
     * two screens a person can have open together.
     */
    public function recalculateRating(): void
    {
        $aggregate = $this->reviews()
            ->published()
            ->toBase()
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as average_rating')
            ->first();

        $count = (int) ($aggregate->review_count ?? 0);

        DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update([
                'review_count' => $count,
                // A zero rather than null: the column is NOT NULL default 0, and
                // "unrated" is expressed by review_count, not by a null score.
                'rating' => $count > 0 ? round((float) $aggregate->average_rating, 2) : 0,
            ]);
    }
}
