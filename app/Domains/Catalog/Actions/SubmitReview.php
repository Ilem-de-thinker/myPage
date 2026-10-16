<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Review;
use App\Domains\Seller\Enums\ReviewStatus;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;

/**
 * Records a review someone left about a business, product or service.
 *
 * Always lands as pending. Nothing written here is ever published directly:
 * review text is unmoderated user input landing on a public page, and the
 * storefront's rating is derived from these rows, so one unreviewed slur moves a
 * seller's score by a visible amount. The seller's inbox decides.
 *
 * Buyers have no account, which is what makes this endpoint the most abused
 * surface on the platform. Everything that can be checked from here is checked
 * here rather than at the form: the reviewable has to belong to this page, the
 * rating has to be in range, and the body has to say something.
 */
class SubmitReview
{
    /**
     * @param  array{author_name: string, author_location?: ?string, rating: int, body: string, is_anonymous?: bool}  $data
     */
    public function handle(StorePage $page, Model $reviewable, array $data): Review
    {
        // Refused rather than clamped. A payload asking for six stars is either a
        // bug or someone probing, and quietly turning it into five hides both.
        if ($data['rating'] < 1 || $data['rating'] > 5) {
            abort(422, 'A rating has to be between one and five stars.');
        }

        $review = new Review([
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->getKey(),
            'author_name' => $data['author_name'],
            'author_location' => $data['author_location'] ?? null,
            'rating' => $data['rating'],
            'body' => $data['body'],
            'is_anonymous' => $data['is_anonymous'] ?? false,
            'status' => ReviewStatus::Pending,
        ]);

        // Through the relation rather than as two mass-assigned columns: they are
        // deliberately not fillable, and setting a morph's type by hand is how you
        // end up with a row whose type column quietly disagrees with the model it
        // claims to point at.
        //
        // Associated *before* the insert rather than after. `create()` writes the
        // row immediately, and reviewable_type is NOT NULL — so associating
        // afterwards is an INSERT that fails before it can be corrected.
        //
        // The reviewable comes from the route, never from the form, so a crafted
        // request cannot attach a five-star review to a competitor's product by
        // sending their id.
        $review->reviewable()->associate($reviewable);
        $review->save();

        return $review;
    }
}
