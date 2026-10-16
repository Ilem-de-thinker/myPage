<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real reviews, and the aggregates they roll up into.
     *
     * The `reviews` block on a storefront home page has always been a seller-typed
     * testimonial repeater. That is the seller writing their own customers' praise,
     * which is worth having and is not a review: nothing here reads it, and
     * nothing anywhere aggregates it.
     *
     * A reviewable is the business, one of its products, or one of its services.
     * A morph rather than two nullable foreign keys, because all three reviewable
     * types need the identical relation, the identical published scope and the
     * identical recalculate step (App\Shared\Traits\HasRating). Three nullable FKs
     * would mean three hand-written copies of that logic, and the first one to
     * drift would be the one nobody looked at.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            // Denormalised onto every row so the seller inbox is one query scoped
            // by seller, with no join through whatever the review is about.
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_page_id')->constrained()->cascadeOnDelete();

            $table->morphs('reviewable');

            // Buyers have no account — the platform's whole premise is that
            // browsing and buying need no sign-in — so the name is typed, not
            // resolved. Length-capped, and never rendered without escaping.
            $table->string('author_name', 80);
            $table->string('author_location', 80)->nullable();

            // 1 to 5. Constrained in the model and in the form request rather
            // than by the column: the same number drives a filter, a star count
            // and a JSON-LD aggregate, and six stars has to be impossible on the
            // way in rather than caught three layers later.
            $table->unsignedTinyInteger('rating');

            $table->text('body');
            $table->boolean('is_anonymous')->default(false);

            // pending | approved | rejected. Nothing renders before approval: an
            // unreviewed comment on a public page is a legal and reputational
            // problem, not a moderation nicety.
            $table->string('status', 16)->default('pending');

            // The seller's pick of which review leads. Separate from approval, so
            // a seller can stop featuring something without un-publishing it.
            $table->boolean('is_featured')->default(false);

            // Set on approval, not on submission. A pending review has no publish
            // date, and "newest first" must not reorder while a seller works
            // through the queue.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['store_page_id', 'status'], 'reviews_inbox_index');
            $table->index('reviewable_type', 'reviews_type_index');
        });

        // The aggregates.
        //
        // Cached on the row rather than aggregated at read time. A catalogue grid
        // of twelve products each running a COUNT would be thirteen queries to
        // draw a screen of stars, and the answer changes only when a review is
        // approved — which is an event, not a per-request question.
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('rating', 3, 2)->default(0)->after('view_count');
            $table->unsignedInteger('review_count')->default(0)->after('rating');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->decimal('rating', 3, 2)->default(0)->after('view_count');
            $table->unsignedInteger('review_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['rating', 'review_count']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['rating', 'review_count']);
        });

        Schema::dropIfExists('reviews');
    }
};
