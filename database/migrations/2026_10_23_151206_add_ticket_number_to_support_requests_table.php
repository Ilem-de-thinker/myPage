<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->string('ticket_number', 24)->nullable()->after('id');
        });

        DB::table('support_requests')->orderBy('id')->eachById(function (object $supportRequest): void {
            DB::table('support_requests')
                ->where('id', $supportRequest->id)
                ->update(['ticket_number' => 'MP-'.str_pad((string) $supportRequest->id, 8, '0', STR_PAD_LEFT)]);
        });

        Schema::table('support_requests', function (Blueprint $table): void {
            $table->unique('ticket_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
