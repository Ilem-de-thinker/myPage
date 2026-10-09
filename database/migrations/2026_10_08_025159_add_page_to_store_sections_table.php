<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_sections', function (Blueprint $table) {
            $table->string('page', 32)->default('home')->after('store_page_id');
            $table->dropUnique(['store_page_id', 'key']);
            $table->unique(['store_page_id', 'page', 'key']);
            $table->index(['store_page_id', 'page', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('store_sections', function (Blueprint $table) {
            $table->dropUnique(['store_page_id', 'page', 'key']);
            $table->dropIndex(['store_page_id', 'page', 'position']);
            $table->unique(['store_page_id', 'key']);
            $table->dropColumn('page');
        });
    }
};
