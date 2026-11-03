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
        Schema::table('ai_drafts', function (Blueprint $table) {
            $table->foreignId('ai_task_id')->nullable()->after('store_page_id')->constrained('ai_tasks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_task_id');
        });
    }
};
