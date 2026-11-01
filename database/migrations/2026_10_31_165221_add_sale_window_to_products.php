<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // compare_at_kobo already exists; these only decide when it is live.
            // Null start means "already started", null end means "no end".
            $table->timestamp('sale_starts_at')->nullable()->after('compare_at_kobo');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_starts_at', 'sale_ends_at']);
        });
    }
};
