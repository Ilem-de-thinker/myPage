<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('compare_at_kobo')->nullable()->after('price_kobo');
            // Same window rules as products: computed on read, never flagged.
            $table->timestamp('sale_starts_at')->nullable()->after('compare_at_kobo');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['compare_at_kobo', 'sale_starts_at', 'sale_ends_at']);
        });
    }
};
