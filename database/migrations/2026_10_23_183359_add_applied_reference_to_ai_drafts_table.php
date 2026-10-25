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
        Schema::table('ai_drafts', function (Blueprint $table): void {
            $table->string('applied_type')->nullable()->after('reviewed_by');
            $table->unsignedBigInteger('applied_id')->nullable()->after('applied_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_drafts', function (Blueprint $table): void {
            $table->dropColumn(['applied_type', 'applied_id']);
        });
    }
};
