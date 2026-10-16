<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The catalog needs a popularity signal to sort by.
     *
     * `best sellers` cannot be derived from the products table alone — a product
     * with no order lines is indistinguishable from one nobody wanted — so the
     * sort engine counts lines in `order_items` in a subquery and this counter
     * carries the cheap half of the signal: raw traffic.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('view_count')->default(0)->after('is_featured');

            // `price_low` / `price_high` filter and sort on the same column with
            // the same status guard, which the existing (store_page_id, status)
            // index cannot serve once the sort spans a page's whole catalogue.
            $table->index(['store_page_id', 'status', 'price_kobo'], 'products_catalog_price_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_catalog_price_index');
            $table->dropColumn('view_count');
        });
    }
};
