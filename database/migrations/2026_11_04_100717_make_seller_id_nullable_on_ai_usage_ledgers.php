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
        Schema::table('ai_usage_ledgers', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
        });

        Schema::table('ai_usage_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id')->nullable()->change();
            $table->string('funded_by')->default('merchant')->after('store_page_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_ledgers', function (Blueprint $table) {
            $table->dropIndex(['funded_by']);
            $table->dropColumn('funded_by');
        });

        Schema::table('ai_usage_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id')->nullable(false)->change();
            $table->foreign('seller_id')->references('id')->on('sellers')->cascadeOnDelete();
        });
    }
};
