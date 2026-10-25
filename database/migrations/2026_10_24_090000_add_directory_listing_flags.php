<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_directory_listed')->default(true)->after('status');
            $table->index(['is_directory_listed', 'status', 'price_kobo'], 'products_directory_index');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('is_directory_listed')->default(true)->after('is_active');
            $table->index(['is_directory_listed', 'is_active', 'price_kobo'], 'services_directory_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_directory_index');
            $table->dropColumn('is_directory_listed');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_directory_index');
            $table->dropColumn('is_directory_listed');
        });
    }
};
