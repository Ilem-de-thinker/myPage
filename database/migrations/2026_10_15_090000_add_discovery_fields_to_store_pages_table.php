<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything the discovery hub needs to know about a page.
     *
     * The brief described these as columns on a `businesses` table. There is no
     * such table: the public business is `store_pages`, which already carries the
     * two other things the discovery screen filters on — `theme` (the store /
     * service split) and `city`. So this migration adds only what is genuinely
     * absent, and re-uses the rest.
     *
     * Every column defaults, so this runs against live rows without a backfill
     * pass: a seller's page that existed before discovery existed is simply not
     * opted in, which is the correct state for it.
     */
    public function up(): void
    {
        Schema::table('store_pages', function (Blueprint $table): void {
            // Opting in is the seller's decision, not the platform's. A page that
            // has never been asked cannot be assumed to want to be in a public
            // directory, so the default is off and nothing lists until it is on.
            $table->boolean('is_directory_opted_in')->default(false)->after('published_at');

            // 1 to 4, mirroring PriceTier. Left nullable because it is a rough
            // signal a seller sets once ("what kind of shop am I?"), not a price.
            $table->unsignedTinyInteger('price_tier')->nullable()->after('is_directory_opted_in');

            // A cached aggregate, not a computed join. There is no review table
            // to count from yet, so whatever eventually writes reviews owns
            // keeping these two in step with each other.
            $table->decimal('rating', 3, 2)->default(0)->after('price_tier');
            $table->unsignedInteger('review_count')->default(0)->after('rating');

            // Paid placement and the top of the marquee. Separate from
            // `verified_at`, which is a trust signal and not a marketing one.
            $table->boolean('is_featured')->default(false)->after('review_count');

            // Backs the `popular` sort. Incremented on read, not written per hit,
            // so a scraper cannot turn one page into a write amplifier.
            $table->unsignedInteger('view_count')->default(0)->after('is_featured');

            // The discovery listing's only hot path: published + opted in, then
            // narrowed by theme or city. One covering index rather than the three
            // single-column ones this query would otherwise use.
            $table->index(
                ['is_directory_opted_in', 'status', 'theme', 'city'],
                'store_pages_discovery_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('store_pages', function (Blueprint $table): void {
            $table->dropIndex('store_pages_discovery_index');

            $table->dropColumn([
                'is_directory_opted_in',
                'price_tier',
                'rating',
                'review_count',
                'is_featured',
                'view_count',
            ]);
        });
    }
};
