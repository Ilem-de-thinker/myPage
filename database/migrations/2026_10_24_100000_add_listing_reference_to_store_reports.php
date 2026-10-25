<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_reports', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->after('store_page_id')->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->index(['product_id', 'status']);
            $table->index(['service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('store_reports', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['service_id']);
            $table->dropIndex(['product_id', 'status']);
            $table->dropIndex(['service_id', 'status']);
            $table->dropColumn(['product_id', 'service_id']);
        });
    }
};
