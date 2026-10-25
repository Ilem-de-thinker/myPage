<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StorePage> */
class StorePageFactory extends Factory
{
    protected $model = StorePage::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'slug' => fake()->slug(2),
            'name' => fake()->company(),
            'theme' => Theme::Store,
            'status' => 'draft',
            'tagline' => fake()->sentence(),
            'bio' => fake()->paragraph(),
            'whatsapp_number' => '+2348030000000',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'brand_color' => '#16a34a',
            // A store page in a test is a working shop, and a working shop has
            // been through verification — the dashboard gate refuses everything
            // else. The column is deliberately not fillable, so it is written
            // after construction rather than through create().
            'verified_at' => now(),
        ];
    }

    /**
     * The default, named so a test can be explicit about needing it.
     */
    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }

    /**
     * A page an admin has not looked at yet: no dashboard, no writes, no
     * dashboard. This is the state a seller is in between registering and their
     * badge application being decided.
     */
    public function unverified(): static
    {
        return $this->state(fn () => ['verified_at' => null]);
    }

    public function storeTheme(): static
    {
        return $this->state(fn () => ['theme' => Theme::Store]);
    }

    public function serviceTheme(): static
    {
        return $this->state(fn () => ['theme' => Theme::Service]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }

    /**
     * Published *and* opted into the public discovery hub.
     *
     * Three conditions have to hold together: published status, a published_at,
     * and the seller's own opt-in. A factory state that only set the flag would
     * let a test pass against a page that could never actually be listed, which
     * is exactly the case the discovery query exists to exclude.
     */
    public function discoverable(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'is_directory_opted_in' => true,
            'is_featured' => false,
            'rating' => fake()->randomFloat(1, 30, 50) / 10,
            'review_count' => fake()->numberBetween(1, 240),
            'price_tier' => fake()->numberBetween(1, 4),
        ]);
    }

    /**
     * Opted in and paying for placement. Reuses discoverable() so a featured page
     * is never featured *and* unpublished.
     */
    public function featured(): static
    {
        return $this->discoverable()->state(fn () => ['is_featured' => true]);
    }

    /**
     * A page that looks opted in but is suspended. For the rule that nothing
     * suspended is ever listed.
     */
    public function suspended(): static
    {
        return $this->discoverable()->state(fn () => ['status' => 'suspended']);
    }

    /**
     * A page that has been written to: every block its theme declares carries
     * some placeholder copy.
     *
     * This replaces the old withSections(), which switched blocks on. Onboarding
     * no longer writes empty rows for a seller to fill in later, so a factory
     * page meant to be published needs actual content or nothing renders.
     */
    public function withContent(): static
    {
        return $this->afterCreating(function (StorePage $page) {
            foreach ($page->theme->pages() as $pageKey => $definition) {
                foreach ($definition['blocks'] as $key) {
                    $schema = config("themes.{$page->theme->value}.blocks.".str_replace('.', '_', $pageKey.'.'.$key), []);
                    $content = [];

                    foreach ($schema['fields'] ?? [] as $name => $field) {
                        $content[$name] = match ($field['type']) {
                            'repeater' => [['quote' => fake()->sentence(), 'author' => fake()->name()]],
                            'image' => null,
                            default => fake()->sentence(),
                        };
                    }

                    $page->blocks()->create([
                        'page' => $pageKey,
                        'key' => $key,
                        'content' => array_filter($content),
                    ]);
                }
            }
        });
    }
}
