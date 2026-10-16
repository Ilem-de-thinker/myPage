<?php

namespace Database\Factories\Domains\Catalog\Models;

use App\Domains\Catalog\Models\Review;
use App\Domains\Seller\Enums\ReviewStatus;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * A review's morph columns are deliberately not fillable, so that a crafted
     * request cannot attach a five-star review to a competitor's product by
     * sending somebody else's id. A factory is not a request, so it writes them
     * unguarded — without this the factory cannot create its own model, because
     * reviews.reviewable_type is NOT NULL.
     */
    public function newModel(array $attributes = [])
    {
        return Model::unguarded(fn (): Review => new Review($attributes));
    }

    public function definition(): array
    {
        return [
            'seller_id' => null,
            'store_page_id' => StorePage::factory(),
            /*
                A reviewable, because the morph columns are NOT NULL and a factory
                that cannot create its own model is a factory every caller has to
                remember to rescue. Defaults to the page this review belongs to;
                forBusiness() and a product review each override it.
            */
            'reviewable_type' => (new StorePage)->getMorphClass(),
            'reviewable_id' => StorePage::factory(),
            // Pending by default, because that is what a real submission is and a
            // test that forgets to approve it should be testing that.
            'status' => ReviewStatus::Pending,
            'author_name' => fake()->name(),
            'author_location' => fake()->randomElement([null, 'Lekki', 'Surulere', 'Ikeja']),
            // 3 to 5 rather than 1 to 5: no real catalogue is a wall of one-star
            // reviews, and a suite seeded from 1..5 produces a mean of three that
            // looks like a bug in whatever assertion reads it.
            'rating' => fake()->numberBetween(3, 5),
            'body' => fake()->paragraph(),
            'is_anonymous' => false,
            'is_featured' => false,
            'published_at' => null,
        ];
    }

    /**
     * A review of the business itself, rather than of one of its products.
     */
    public function forBusiness(): static
    {
        return $this->state(fn () => [
            'reviewable_type' => (new StorePage)->getMorphClass(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::Approved,
            'published_at' => now()->subDays(fake()->numberBetween(0, 60)),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ReviewStatus::Rejected]);
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['is_anonymous' => true]);
    }
}
