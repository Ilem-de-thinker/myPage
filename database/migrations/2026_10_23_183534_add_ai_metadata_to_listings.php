<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('ai_metadata')->nullable()->after('description');
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->json('ai_metadata')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('ai_metadata');
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('ai_metadata');
        });
    }
};
