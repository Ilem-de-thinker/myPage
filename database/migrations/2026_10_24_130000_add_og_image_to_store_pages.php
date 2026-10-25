<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_pages', function (Blueprint $table): void {
            $table->string('og_image_path')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('store_pages', function (Blueprint $table): void {
            $table->dropColumn('og_image_path');
        });
    }
};
