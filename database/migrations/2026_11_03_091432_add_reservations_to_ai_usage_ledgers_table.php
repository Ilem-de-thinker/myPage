<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A queued operation must consume its credit before a provider call exists,
     * otherwise parallel queueing can all pass the same remaining-credit check.
     * Pending rows are reservations; successful rows become "used", and failed
     * or cancelled work deletes its pending row.
     */
    public function up(): void
    {
        Schema::table('ai_usage_ledgers', function (Blueprint $table) {
            $table->string('status', 16)->default('used')->after('task')->index();
            $table->foreignId('operation_id')->nullable()->unique()->after('store_page_id')->constrained('ai_operations')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable()->after('estimated_cost');
            $table->index(['seller_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_ledgers', function (Blueprint $table) {
            $table->dropUnique(['operation_id']);
            $table->dropForeign(['operation_id']);
            $table->dropIndex(['seller_id', 'status', 'created_at']);
            $table->dropColumn(['status', 'operation_id', 'reserved_at']);
        });
    }
};
