<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SKUs only have to be unique inside one shop. Two sellers may both sell
     * "BAG-001"; what must never happen is a duplicate inside the same store.
     * store_page_id is denormalised onto the variant so a composite unique index
     * can enforce that at the database edge, not just in form validation.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('store_page_id')->nullable()->after('product_id')->constrained('store_pages')->cascadeOnDelete();
        });

        DB::statement('update product_variants set store_page_id = (select store_page_id from products where products.id = product_variants.product_id)');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['store_page_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['store_page_id', 'sku']);
            $table->dropForeign(['store_page_id']);
            $table->dropColumn('store_page_id');
        });
    }
};
