<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * store_sections became page_blocks.
 *
 * The old table was a page builder: `position` let a seller reorder blocks and
 * `is_enabled` let them hide them. Part 9 of the plan revokes both — each theme
 * is a fixed website and the only design setting is brand colour. What survives
 * is the content: the words and pictures a seller wrote, which must not be lost
 * in the rename.
 */
return new class extends Migration
{
    /** Blocks that are still part of either theme. Anything else is stale. */
    private const KEEP = ['hero', 'featured', 'categories', 'products', 'reviews', 'about', 'contact'];

    public function up(): void
    {
        Schema::rename('store_sections', 'page_blocks');

        // The builder allowed duplicates, which rendered the same component twice
        // with no way for a seller to tell the two apart. Keep the oldest.
        DB::table('page_blocks')->orderBy('id')->get()
            ->groupBy(fn ($block) => $block->store_page_id.'|'.$block->page.'|'.$block->key)
            ->filter(fn ($group) => $group->count() > 1)
            ->each(function ($group): void {
                DB::table('page_blocks')->whereIn('id', $group->pluck('id')->slice(1))->delete();
            });

        // A block with no content renders as nothing now, so carrying it forward
        // only means the seller sees blank inputs for something never written.
        DB::table('page_blocks')
            ->where(function ($query): void {
                $query->whereNull('content')
                    ->orWhere('content', '{}')
                    ->orWhere('content', 'null');
            })
            ->delete();

        DB::table('page_blocks')->whereNotIn('key', self::KEEP)->delete();

        Schema::table('page_blocks', function (Blueprint $table) {
            // SQLite refuses to drop a column an index still references, so the
            // index over position has to go first. Named explicitly: after the
            // rename, Laravel would derive the index name from the new table and
            // look for an index that does not exist under that name.
            $table->dropIndex('store_sections_store_page_id_page_position_index');

            // An earlier migration already made this triple unique. Renaming the
            // table leaves its index behind under the old name, so it is dropped
            // explicitly and recreated below under a stable one.
            $table->dropIndex('store_sections_store_page_id_page_key_unique');

            $table->dropColumn(['position', 'is_enabled']);

            $table->unique(['store_page_id', 'page', 'key'], 'page_block_unique');
        });
    }

    public function down(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->dropUnique('page_block_unique');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
        });

        Schema::rename('page_blocks', 'store_sections');
    }
};
