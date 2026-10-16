<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 9 fixes one typeface per theme. font_pair let a seller pick between four,
 * which was the only other "design" control and made two shops on the same theme
 * look like different products.
 *
 * The column is dropped rather than ignored: leaving it means the next agent
 * finds a working font picker and wires it back up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('store_pages', 'font_pair')) {
            Schema::table('store_pages', function (Blueprint $table) {
                $table->dropColumn('font_pair');
            });
        }
    }

    public function down(): void
    {
        Schema::table('store_pages', function (Blueprint $table) {
            $table->string('font_pair', 32)->default('modern')->after('brand_color');
        });
    }
};
